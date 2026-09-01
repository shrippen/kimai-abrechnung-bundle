<?php

namespace KimaiPlugin\AbrechnungBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Timesheet;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\AbrechnungBundle\Repository\OpenItemsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/abrechnung')]
class AbrechnungController extends AbstractController
{
    public function __construct(
        private readonly OpenItemsRepository $openItemsRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly UserRepository $userRepository,
        private readonly TimesheetService $timesheetService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '', name: 'abrechnung_index', methods: ['GET'])]
    #[IsGranted('view_invoice')]
    public function index(Request $request): Response
    {
        $user = null;
        $customer = null;
        $dateFrom = null;
        $dateTo = null;
        $month = $request->query->get('month', '');
        $year = $request->query->get('year', '');

        // Filter by customer
        $customerId = $request->query->get('customer', '');
        if ($customerId !== '' && $customerId !== '0') {
            $customer = $this->customerRepository->find((int) $customerId);
        }

        // Filter by user
        $userId = $request->query->get('user', '');
        if ($userId !== '' && $userId !== '0') {
            $user = $this->userRepository->find((int) $userId);
        }

        // Filter by month/year
        if ($year !== '' && $month !== '') {
            $dateFrom = new \DateTimeImmutable(sprintf('%s-%s-01', $year, str_pad($month, 2, '0', STR_PAD_LEFT)));
            $dateTo = $dateFrom->modify('last day of this month 23:59:59');
        } elseif ($year !== '') {
            $dateFrom = new \DateTimeImmutable(sprintf('%s-01-01', $year));
            $dateTo = new \DateTimeImmutable(sprintf('%s-12-31 23:59:59', $year));
        }

        $groups = $this->openItemsRepository->findGroupedByCustomer($user, $customer, $dateFrom, $dateTo);

        // Re-group: Customer → Project → Items
        $structured = [];
        foreach ($groups as $customerId => $group) {
            $projectGroups = [];
            foreach ($group['items'] as $item) {
                $pid = $item->getProject()->getId();
                if (!isset($projectGroups[$pid])) {
                    $projectGroups[$pid] = [
                        'project' => $item->getProject(),
                        'items' => [],
                        'totalDuration' => 0,
                        'totalRate' => 0.0,
                    ];
                }
                $projectGroups[$pid]['items'][] = $item;
                $projectGroups[$pid]['totalDuration'] += $item->getDuration();
                $projectGroups[$pid]['totalRate'] += $item->getRate() ?? 0.0;
            }
            $structured[$customerId] = [
                'customer' => $group['customer'],
                'projects' => $projectGroups,
                'totalDuration' => $group['totalDuration'],
                'totalRate' => $group['totalRate'],
            ];
        }

        $page = new PageSetup('menu.abrechnung');
        $page->setHelp('abrechnung.html');

        return $this->render('@Abrechnung/abrechnung/index.html.twig', [
            'page_setup' => $page,
            'groups' => $structured,
            'customers' => $this->customerRepository->findBy([], ['name' => 'ASC']),
            'users' => $this->userRepository->findBy([], ['username' => 'ASC']),
            'filter_month' => $month,
            'filter_year' => $year,
            'filter_customer' => $customerId,
            'filter_user' => $userId,
        ]);
    }

    #[Route(path: '/mark', name: 'abrechnung_mark', methods: ['POST'])]
    #[IsGranted('view_invoice')]
    public function mark(Request $request): Response
    {
        $ids = $request->request->all('timesheets');
        $action = $request->request->get('action', 'mark');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (empty($ids)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'no_selection']);
            }
            $this->flashWarning('abrechnung.no_selection');

            return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
        }

        // AJAX toggle: handle single or multiple entries
        if ($isAjax) {
            $states = [];
            foreach ($ids as $id) {
                $timesheet = $this->entityManager->find(Timesheet::class, (int) $id);
                if (!$timesheet instanceof Timesheet) {
                    continue;
                }
                if (!$this->isGranted('edit_export', $timesheet)) {
                    continue;
                }
                $newState = !$timesheet->isExported();
                if (!$newState && !$this->isGranted('edit_exported_timesheet')) {
                    continue;
                }
                $timesheet->setExported($newState);
                $this->timesheetService->saveTimesheet($timesheet);
                $states[(int) $id] = $newState;
            }

            return $this->json(['success' => true, 'states' => $states]);
        }

        $exported = ($action === 'mark');
        $count = 0;

        foreach ($ids as $id) {
            $timesheet = $this->entityManager->find(Timesheet::class, (int) $id);

            if (!$timesheet instanceof Timesheet) {
                continue;
            }

            if (!$this->isGranted('edit_export', $timesheet)) {
                continue;
            }

            if (!$exported && $timesheet->isExported() && !$this->isGranted('edit_exported_timesheet')) {
                continue;
            }

            $timesheet->setExported($exported);
            $this->timesheetService->saveTimesheet($timesheet);
            $count++;
        }

        if ($isAjax) {
            return $this->json(['success' => true, 'count' => $count]);
        }

        if ($exported) {
            $this->flashSuccess('abrechnung.marked_success');
        } else {
            $this->flashSuccess('abrechnung.unmarked_success');
        }

        return $this->redirectToRoute('abrechnung_index', $this->getFilterParams($request));
    }

    private function getFilterParams(Request $request): array
    {
        return array_filter([
            'month' => $request->request->get('month', $request->query->get('month', '')),
            'year' => $request->request->get('year', $request->query->get('year', '')),
            'customer' => $request->request->get('customer', $request->query->get('customer', '')),
            'user' => $request->request->get('user', $request->query->get('user', '')),
        ], fn ($v) => $v !== '' && $v !== null);
    }
}
