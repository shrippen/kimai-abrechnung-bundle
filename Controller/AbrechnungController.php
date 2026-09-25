<?php

namespace KimaiPlugin\AbrechnungBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Customer;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\Query\CustomerFormTypeQuery;
use App\Repository\Query\UserFormTypeQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use KimaiPlugin\AbrechnungBundle\Repository\OpenItemsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/abrechnung')]
class AbrechnungController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'abrechnung.mark';

    public function __construct(
        private readonly OpenItemsRepository $openItemsRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly UserRepository $userRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly TimesheetService $timesheetService,
    ) {
    }

    #[Route(path: '', name: 'abrechnung_index', methods: ['GET'])]
    #[IsGranted('view_invoice')]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();
        $factory = $this->getDateTimeFactory();
        $dateFrom = null;
        $dateTo = null;

        // Invalid values are ignored (treated as "all") instead of failing
        $year = $this->getIntParam($request, 'year', 1970, 2999);
        $month = $this->getIntParam($request, 'month', 1, 12);

        // Only customers and users the current user may see can be filtered (team scoping)
        $customers = $this->getVisibleCustomers($currentUser);
        $users = $this->getVisibleUsers($currentUser);

        $customerId = $this->getIntParam($request, 'customer', 1, PHP_INT_MAX);
        $customer = $customerId !== null ? ($customers[$customerId] ?? null) : null;

        $userId = $this->getIntParam($request, 'user', 1, PHP_INT_MAX);
        $user = $userId !== null ? ($users[$userId] ?? null) : null;

        // Month boundaries in the timezone of the current user
        if ($year !== null && $month !== null) {
            $date = $factory->createDateTime(\sprintf('%04d-%02d-01 12:00:00', $year, $month));
            $dateFrom = $factory->getStartOfMonth($date);
            $dateTo = $factory->getEndOfMonth($date);
        } elseif ($year !== null) {
            $date = $factory->createDateTime(\sprintf('%04d-06-01 12:00:00', $year));
            $dateFrom = $factory->createStartOfYear($date);
            $dateTo = $factory->createEndOfYear($date);
        }

        $groups = $this->openItemsRepository->findGroupedByCustomer($currentUser, $user, $customer, $dateFrom, $dateTo);

        // Re-group: Customer → Project → Items
        // Amounts are only shown (and summed) if the user may see the rate of the entry
        $rateVisible = [];
        $allIds = [];
        $structured = [];
        foreach ($groups as $cid => $group) {
            $projectGroups = [];
            $customerGroup = [
                'customer' => $group['customer'],
                'projects' => [],
                'totalDuration' => 0,
                'totalRate' => 0.0,
                'rateHidden' => false,
                'ids' => [],
            ];
            foreach ($group['items'] as $item) {
                $canViewRate = $this->isGranted('view_rate', $item);
                $rateVisible[$item->getId()] = $canViewRate;

                $pid = $item->getProject()->getId();
                if (!isset($projectGroups[$pid])) {
                    $projectGroups[$pid] = [
                        'project' => $item->getProject(),
                        'items' => [],
                        'totalDuration' => 0,
                        'totalRate' => 0.0,
                        'rateHidden' => false,
                        'ids' => [],
                    ];
                }
                $projectGroups[$pid]['items'][] = $item;
                $projectGroups[$pid]['ids'][] = $item->getId();
                $customerGroup['ids'][] = $item->getId();
                $allIds[] = $item->getId();
                $projectGroups[$pid]['totalDuration'] += $item->getDuration() ?? 0;
                $customerGroup['totalDuration'] += $item->getDuration() ?? 0;
                if ($canViewRate) {
                    $projectGroups[$pid]['totalRate'] += $item->getRate();
                    $customerGroup['totalRate'] += $item->getRate();
                } else {
                    $projectGroups[$pid]['rateHidden'] = true;
                    $customerGroup['rateHidden'] = true;
                }
            }
            $customerGroup['projects'] = $projectGroups;
            $structured[$cid] = $customerGroup;
        }

        $currentYear = (int) $factory->createDateTime()->format('Y');
        $oldestYear = min($currentYear, $this->openItemsRepository->findOldestOpenYear() ?? $currentYear, $year ?? $currentYear);

        $page = new PageSetup('menu.abrechnung');

        return $this->render('@Abrechnung/abrechnung/index.html.twig', [
            'page_setup' => $page,
            'groups' => $structured,
            'rate_visible' => $rateVisible,
            'all_ids' => $allIds,
            'customers' => $customers,
            'users' => $users,
            'years' => range($currentYear, $oldestYear),
            'filter_month' => $month,
            'filter_year' => $year,
            'filter_customer' => $customer?->getId(),
            'filter_user' => $user?->getId(),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]);
    }

    /**
     * Sets (never toggles) the exported state of the given timesheets.
     *
     * Parameters: timesheets[] (IDs), action=mark|unmark, _token (CSRF).
     * AJAX response: {success, states: {id: bool}, skipped: [id], failed: [id]}
     */
    #[Route(path: '/mark', name: 'abrechnung_mark', methods: ['POST'])]
    #[IsGranted('view_invoice')]
    public function mark(Request $request): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'invalid_csrf_token'], Response::HTTP_BAD_REQUEST);
            }
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
        }

        $action = $request->request->get('action');
        if ($action !== 'mark' && $action !== 'unmark') {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'invalid_action'], Response::HTTP_BAD_REQUEST);
            }
            $this->flashError('abrechnung.invalid_action');

            return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $request->request->all('timesheets')), fn (int $id) => $id > 0)));

        if (\count($ids) === 0) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'no_selection'], Response::HTTP_BAD_REQUEST);
            }
            $this->flashWarning('abrechnung.no_selection');

            return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
        }

        $exported = ($action === 'mark');
        $result = $this->setExported($ids, $exported);

        if ($isAjax) {
            return $this->json([
                'success' => \count($result['failed']) === 0,
                'states' => (object) $result['states'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ]);
        }

        if (\count($result['failed']) > 0) {
            $this->flashError('abrechnung.failed');
        } elseif (\count($result['skipped']) > 0) {
            $this->flashWarning('abrechnung.skipped');
        } elseif ($exported) {
            $this->flashSuccess('abrechnung.marked_success');
        } else {
            $this->flashSuccess('abrechnung.unmarked_success');
        }

        return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
    }

    /**
     * Idempotent: entries that already have the requested state are reported with it,
     * entries the user may not change are reported as skipped, errors per entry as failed.
     *
     * @param int[] $ids
     * @return array{states: array<int, bool>, skipped: int[], failed: int[]}
     */
    private function setExported(array $ids, bool $exported): array
    {
        $states = [];
        $skipped = [];
        $failed = [];

        $timesheets = [];
        foreach ($this->timesheetRepository->findBy(['id' => $ids]) as $timesheet) {
            $timesheets[$timesheet->getId()] = $timesheet;
        }

        foreach ($ids as $id) {
            $timesheet = $timesheets[$id] ?? null;

            if (!$timesheet instanceof Timesheet || !$this->isGranted('edit_export', $timesheet)) {
                $skipped[] = $id;
                continue;
            }

            if ($timesheet->isExported() === $exported) {
                $states[$id] = $exported;
                continue;
            }

            // Same rule as Kimai's API (PATCH /api/timesheets/{id}/export)
            if ($timesheet->isExported() && !$this->isGranted('edit_exported_timesheet')) {
                $skipped[] = $id;
                continue;
            }

            try {
                $timesheet->setExported($exported);
                $this->timesheetService->saveTimesheet($timesheet);
                $states[$id] = $exported;
            } catch (\Exception $ex) {
                $this->logException($ex);
                $failed[] = $id;
            }
        }

        return ['states' => $states, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @return array<int, Customer>
     */
    private function getVisibleCustomers(User $currentUser): array
    {
        $query = new CustomerFormTypeQuery();
        $query->setUser($currentUser);

        $customers = [];
        foreach ($this->customerRepository->getQueryBuilderForFormType($query)->getQuery()->getResult() as $customer) {
            $customers[$customer->getId()] = $customer;
        }

        return $customers;
    }

    /**
     * Active users of the teams the current user leads, plus himself (all for admins).
     *
     * @return array<int, User>
     */
    private function getVisibleUsers(User $currentUser): array
    {
        $query = new UserFormTypeQuery();
        $query->setUser($currentUser);

        $users = [];
        foreach ($this->userRepository->getQueryBuilderForFormType($query)->getQuery()->getResult() as $user) {
            $users[$user->getId()] = $user;
        }

        return $users;
    }

    private function getIntParam(Request $request, string $name, int $min, int $max): ?int
    {
        $value = filter_var($request->query->get($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        return $value === false ? null : $value;
    }

    private function getFilterParams(Request $request): array
    {
        $params = [];
        foreach (['month', 'year', 'customer', 'user'] as $name) {
            $value = filter_var($request->request->get($name, $request->query->get($name)), FILTER_VALIDATE_INT);
            if ($value !== false) {
                $params[$name] = $value;
            }
        }

        return $params;
    }
}
