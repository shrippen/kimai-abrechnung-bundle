<?php

namespace KimaiPlugin\AbrechnungBundle\Controller;

use App\Configuration\LocaleService;
use App\Controller\AbstractController;
use App\Entity\Timesheet;
use App\Form\Type\DateRangeType;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Utils\DataTable;
use App\Utils\LocaleFormatter;
use App\Utils\PageSetup;
use KimaiPlugin\AbrechnungBundle\Form\AbrechnungToolbarForm;
use KimaiPlugin\AbrechnungBundle\Repository\OpenItemsRepository;
use KimaiPlugin\AbrechnungBundle\Repository\Query\AbrechnungQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/abrechnung')]
class AbrechnungController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'abrechnung.mark';
    public const HELP_URL = 'https://github.com/shrippen/kimai-abrechnung-bundle/blob/main/docs/abrechnung.md';

    /**
     * Entries marked in this session may be reopened ("Undo") for this many seconds,
     * even without the permission edit_exported_timesheet (team leads).
     */
    public const UNDO_GRACE_SECONDS = 900;
    private const UNDO_SESSION_KEY = 'abrechnung.undo_grace';

    public function __construct(
        private readonly OpenItemsRepository $openItemsRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly TimesheetService $timesheetService,
        private readonly LocaleService $localeService,
        private readonly TranslatorInterface $translator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '', name: 'abrechnung_index', methods: ['GET'])]
    #[IsGranted('view_invoice')]
    public function index(Request $request): Response
    {
        // Links from plugin version 1.x (?year=2025&month=5&customer=1&user=2)
        if (($legacy = $this->getLegacyParams($request)) !== null) {
            return $this->redirectToRoute('abrechnung_index', $legacy);
        }

        $currentUser = $this->getUser();
        $factory = $this->getDateTimeFactory();
        $formatter = new LocaleFormatter($this->localeService, $request->getLocale());

        $query = new AbrechnungQuery();
        $query->setCurrentUser($currentUser);

        $form = $this->createSearchForm(AbrechnungToolbarForm::class, $query, [
            'action' => $this->generateUrl('abrechnung_index'),
        ]);

        // the period is navigation, not part of a saved default filter (bookmark)
        if ($this->handleSearch($form, $request, ['period'])) {
            return $this->redirectToRoute('abrechnung_index');
        }

        // Period boundaries in the timezone of the current user
        $dateFrom = null;
        $dateTo = null;
        $periodDate = null;
        $period = $query->getPeriod();
        $unit = $query->getPeriodUnit();
        if ($unit === 'month') {
            $periodDate = $factory->createDateTime($period . '-01 12:00:00');
            $dateFrom = $factory->getStartOfMonth($periodDate);
            $dateTo = $factory->getEndOfMonth($periodDate);
        } elseif ($unit === 'year') {
            $periodDate = $factory->createDateTime($period . '-06-01 12:00:00');
            $dateFrom = $factory->createStartOfYear($periodDate);
            $dateTo = $factory->createEndOfYear($periodDate);
        }

        $items = $this->openItemsRepository->findItems($currentUser, $query, $dateFrom, $dateTo);
        $data = $this->buildGroups($items);

        $today = $factory->createDateTime();
        $currentMonth = $today->format('Y-m');
        $currentYear = $today->format('Y');

        if ($unit === 'month') {
            $periodLabel = $formatter->monthName($periodDate, true);
        } elseif ($unit === 'year') {
            $periodLabel = $period;
        } else {
            $periodLabel = $this->translator->trans('abrechnung.period_all');
        }

        $baseParams = $this->getFilterParams($query);
        $nav = [
            'unit' => $unit,
            'label' => $periodLabel,
            'prev' => null,
            'next' => null,
            'today' => null,
            'units' => [
                'month' => $this->periodUrl($baseParams, $unit === 'year' && $period !== $currentYear ? $period . '-01' : $currentMonth),
                'year' => $this->periodUrl($baseParams, $periodDate !== null ? $periodDate->format('Y') : $currentYear),
            ],
        ];
        if ($unit === 'month') {
            $nav['prev'] = $this->periodUrl($baseParams, AbrechnungQuery::normalizePeriod((clone $periodDate)->modify('-1 month')->format('Y-m')));
            $nav['next'] = $this->periodUrl($baseParams, AbrechnungQuery::normalizePeriod((clone $periodDate)->modify('+1 month')->format('Y-m')));
            $nav['today'] = $period !== $currentMonth ? $this->periodUrl($baseParams, $currentMonth) : null;
        } elseif ($unit === 'year') {
            $nav['prev'] = $this->periodUrl($baseParams, AbrechnungQuery::normalizePeriod((string) ((int) $period - 1)));
            $nav['next'] = $this->periodUrl($baseParams, AbrechnungQuery::normalizePeriod((string) ((int) $period + 1)));
            $nav['today'] = $period !== $currentYear ? $this->periodUrl($baseParams, $currentYear) : null;
        } else {
            $nav['today'] = $this->periodUrl($baseParams, $currentMonth);
        }

        // Link to Kimai's export with the same selection (Kimai's own "mark as exported" lives there)
        $exportParams = [
            'performSearch' => 1,
            'exported' => 5, // TimesheetQuery::STATE_NOT_EXPORTED
            'state' => 3, // TimesheetQuery::STATE_STOPPED
            'billable' => 1,
        ];
        if ($query->getState() === AbrechnungQuery::STATE_BILLED) {
            $exportParams['exported'] = 4; // TimesheetQuery::STATE_EXPORTED
        } elseif ($query->getState() === AbrechnungQuery::STATE_ALL) {
            $exportParams['exported'] = 1; // TimesheetQuery::STATE_ALL
        }
        if ($dateFrom !== null && $dateTo !== null) {
            $exportParams['daterange'] = $formatter->dateShort($dateFrom) . DateRangeType::DATE_SPACER . $formatter->dateShort($dateTo);
        }
        foreach (['customers', 'users', 'searchTerm'] as $name) {
            if (\array_key_exists($name, $baseParams)) {
                $exportParams[$name] = $baseParams[$name];
            }
        }

        $page = new PageSetup($this->translator->trans('abrechnung.page_title', ['%period%' => $periodLabel]));
        $page->setHelp(self::HELP_URL);
        $page->setActionName('abrechnung');
        // DataTable only carries the toolbar (search dropdown); the grouped rows use the datatable macros directly
        $table = new DataTable('abrechnung', $query);
        $table->setSearchForm($form);
        $table->deactivateConfiguration();
        $page->setDataTable($table);
        $page->setActionPayload([
            'mark_all' => \count($data['markable_ids']) > 0,
            'export_params' => $exportParams,
        ]);

        return $this->render('@Abrechnung/abrechnung/index.html.twig', [
            'page_setup' => $page,
            'query' => $query,
            'groups' => $data['groups'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'markable_ids' => $data['markable_ids'],
            'unmarkable_ids' => $data['unmarkable_ids'],
            'period_label' => $periodLabel,
            'nav' => $nav,
            'filter_params' => $baseParams,
            'reset_url' => $this->generateUrl('abrechnung_index', ['performSearch' => 1]),
            'billed_url' => $this->generateUrl('abrechnung_index', array_merge($baseParams, ['state' => AbrechnungQuery::STATE_BILLED, 'performSearch' => 1])),
            'can_view_rate' => $this->isGranted('view_rate_own_timesheet') || $this->isGranted('view_rate_other_timesheet'),
            'can_reopen' => $this->isGranted('edit_exported_timesheet'),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]);
    }

    /**
     * Sets (never toggles) the exported state of the given timesheets.
     *
     * Parameters: ids[] (or timesheets[]), action=mark|unmark (body or query), _token (CSRF).
     * JSON response (X-Requested-With: XMLHttpRequest or Accept: application/json):
     *   {success, states: {id: bool}, changed: [id], skipped: [id], failed: [id], message, undo?: {url, token, ids}}
     * Without JS: redirect back to the list with a visible result callout.
     */
    #[Route(path: '/mark', name: 'abrechnung_mark', methods: ['POST'])]
    #[IsGranted('view_invoice')]
    public function mark(Request $request): Response
    {
        $isJson = $request->isXmlHttpRequest() || \in_array('application/json', $request->getAcceptableContentTypes(), true);
        $redirectParams = $this->getRedirectParams($request);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            if ($isJson) {
                return $this->json(['success' => false, 'error' => 'invalid_csrf_token', 'message' => $this->translator->trans('action.csrf.error', [], 'flashmessages')], Response::HTTP_BAD_REQUEST);
            }
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('abrechnung_index', $redirectParams);
        }

        $action = $request->request->get('action', $request->query->get('action'));
        if ($action !== 'mark' && $action !== 'unmark') {
            if ($isJson) {
                return $this->json(['success' => false, 'error' => 'invalid_action', 'message' => $this->translator->trans('abrechnung.invalid_action', [], 'flashmessages')], Response::HTTP_BAD_REQUEST);
            }
            $this->flashError('abrechnung.invalid_action');

            return $this->redirectToRoute('abrechnung_index', $redirectParams);
        }

        $rawIds = array_merge($request->request->all('ids'), $request->request->all('timesheets'));
        $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn (int $id) => $id > 0)));

        if (\count($ids) === 0) {
            if ($isJson) {
                return $this->json(['success' => false, 'error' => 'no_selection', 'message' => $this->translator->trans('abrechnung.no_selection', [], 'flashmessages')], Response::HTTP_BAD_REQUEST);
            }
            $this->flashWarning('abrechnung.no_selection');

            return $this->redirectToRoute('abrechnung_index', $redirectParams);
        }

        $exported = ($action === 'mark');
        $result = $this->setExported($request, $ids, $exported);
        $message = $this->buildResultMessage($result, $exported);

        $undo = null;
        if (\count($result['changed']) > 0) {
            $undo = [
                'url' => $this->generateUrl('abrechnung_mark', ['action' => $exported ? 'unmark' : 'mark']),
                'token' => $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
                'ids' => $result['changed'],
            ];
        }

        if ($isJson) {
            $nothingDone = \count($result['changed']) === 0 && (\count($result['skipped']) > 0 || \count($result['failed']) > 0);

            return $this->json([
                'success' => \count($result['failed']) === 0 && \count($result['skipped']) === 0,
                'states' => (object) $result['states'],
                'changed' => $result['changed'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'message' => $message,
                'undo' => $undo,
            ], $nothingDone ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
        }

        // Kimai hides success flashes, the kit shows this one as info callout above the list
        $this->addFlash('kpu_result', $message);

        return $this->redirectToRoute('abrechnung_index', $redirectParams);
    }

    /**
     * @param Timesheet[] $items
     * @return array{groups: array<int, array<string, mixed>>, rows: array<int, array<string, bool>>, totals: array<string, mixed>, markable_ids: int[], unmarkable_ids: int[]}
     */
    private function buildGroups(array $items): array
    {
        $canReopen = $this->isGranted('edit_exported_timesheet');
        $groups = [];
        $rows = [];
        $markable = [];
        $unmarkable = [];
        $totals = ['duration' => 0, 'count' => 0, 'amounts' => [], 'rate_hidden' => false, 'customers' => 0];

        foreach ($items as $item) {
            $id = $item->getId();
            $project = $item->getProject();
            $customer = $project->getCustomer();
            $cid = $customer->getId();
            $pid = $project->getId();
            $currency = $customer->getCurrency();

            $row = [
                'rate_visible' => $this->isGranted('view_rate', $item),
                'can_export' => $this->isGranted('edit_export', $item),
                'can_edit' => $this->isGranted('edit', $item),
            ];
            $row['can_mark'] = $row['can_export'] && !$item->isExported();
            $row['can_unmark'] = $row['can_export'] && $item->isExported() && $canReopen;
            $rows[$id] = $row;

            if (!isset($groups[$cid])) {
                $groups[$cid] = [
                    'customer' => $customer,
                    'currency' => $currency,
                    'projects' => [],
                    'duration' => 0,
                    'amount' => 0.0,
                    'rate_hidden' => false,
                    'markable_ids' => [],
                    'unmarkable_ids' => [],
                    'selectable_ids' => [],
                ];
            }
            if (!isset($groups[$cid]['projects'][$pid])) {
                $groups[$cid]['projects'][$pid] = [
                    'project' => $project,
                    'items' => [],
                    'duration' => 0,
                    'amount' => 0.0,
                    'rate_hidden' => false,
                    'markable_ids' => [],
                    'unmarkable_ids' => [],
                    'selectable_ids' => [],
                ];
            }

            $projectGroup = &$groups[$cid]['projects'][$pid];
            $customerGroup = &$groups[$cid];

            $projectGroup['items'][] = $item;
            $duration = $item->getDuration() ?? 0;
            $projectGroup['duration'] += $duration;
            $customerGroup['duration'] += $duration;
            $totals['duration'] += $duration;
            $totals['count']++;

            if ($row['rate_visible']) {
                $projectGroup['amount'] += $item->getRate();
                $customerGroup['amount'] += $item->getRate();
                $totals['amounts'][$currency] = ($totals['amounts'][$currency] ?? 0.0) + $item->getRate();
            } else {
                $projectGroup['rate_hidden'] = true;
                $customerGroup['rate_hidden'] = true;
                $totals['rate_hidden'] = true;
            }

            if ($row['can_mark']) {
                $projectGroup['markable_ids'][] = $id;
                $customerGroup['markable_ids'][] = $id;
                $markable[] = $id;
            }
            if ($row['can_unmark']) {
                $projectGroup['unmarkable_ids'][] = $id;
                $customerGroup['unmarkable_ids'][] = $id;
                $unmarkable[] = $id;
            }
            if ($row['can_mark'] || $row['can_unmark']) {
                $projectGroup['selectable_ids'][] = $id;
                $customerGroup['selectable_ids'][] = $id;
            }

            unset($projectGroup, $customerGroup);
        }

        uasort($groups, fn (array $a, array $b) => strcasecmp((string) $a['customer']->getName(), (string) $b['customer']->getName()));
        foreach ($groups as &$group) {
            uasort($group['projects'], fn (array $a, array $b) => strcasecmp((string) $a['project']->getName(), (string) $b['project']->getName()));
        }
        unset($group);

        $totals['customers'] = \count($groups);

        return ['groups' => $groups, 'rows' => $rows, 'totals' => $totals, 'markable_ids' => $markable, 'unmarkable_ids' => $unmarkable];
    }

    /**
     * Idempotent: entries that already have the requested state are reported with it,
     * entries the user may not change are reported as skipped, errors per entry as failed.
     *
     * @param int[] $ids
     * @return array{states: array<int, bool>, changed: int[], skipped: int[], failed: int[]}
     */
    private function setExported(Request $request, array $ids, bool $exported): array
    {
        $states = [];
        $changed = [];
        $skipped = [];
        $failed = [];

        $session = $request->getSession();
        $now = time();
        $grace = array_filter(
            (array) $session->get(self::UNDO_SESSION_KEY, []),
            fn ($time) => \is_int($time) && $time >= $now - self::UNDO_GRACE_SECONDS
        );
        $canReopen = $this->isGranted('edit_exported_timesheet');

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

            // Same rule as Kimai's API (PATCH /api/timesheets/{id}/export), except for
            // "Undo" of entries this session marked a few minutes ago
            if ($timesheet->isExported() && !$canReopen && !isset($grace[$id])) {
                $skipped[] = $id;
                continue;
            }

            try {
                $timesheet->setExported($exported);
                $this->timesheetService->saveTimesheet($timesheet);
                $states[$id] = $exported;
                $changed[] = $id;
                if ($exported) {
                    $grace[$id] = $now;
                } else {
                    unset($grace[$id]);
                }
            } catch (\Exception $ex) {
                $this->logException($ex);
                $failed[] = $id;
            }
        }

        $session->set(self::UNDO_SESSION_KEY, $grace);

        return ['states' => $states, 'changed' => $changed, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @param array{states: array<int, bool>, changed: int[], skipped: int[], failed: int[]} $result
     */
    private function buildResultMessage(array $result, bool $exported): string
    {
        $parts = [];
        $changed = \count($result['changed']);
        if ($changed > 0) {
            $parts[] = $this->translator->trans($exported ? 'abrechnung.result.marked' : 'abrechnung.result.unmarked', ['%count%' => $changed]);
        } elseif (\count($result['skipped']) === 0 && \count($result['failed']) === 0) {
            $parts[] = $this->translator->trans('abrechnung.result.unchanged');
        }
        if (\count($result['skipped']) > 0) {
            $parts[] = $this->translator->trans('abrechnung.result.skipped', ['%count%' => \count($result['skipped'])]);
        }
        if (\count($result['failed']) > 0) {
            $parts[] = $this->translator->trans('abrechnung.result.failed', ['%count%' => \count($result['failed'])]);
        }

        return implode(' · ', $parts);
    }

    /**
     * Effective filter as URL parameters (used for period navigation, export link and redirects).
     *
     * @return array<string, mixed>
     */
    private function getFilterParams(AbrechnungQuery $query): array
    {
        $params = [];
        if (\count($query->getCustomers()) > 0) {
            $params['customers'] = array_values(array_map(fn ($c) => $c->getId(), $query->getCustomers()));
        }
        if (\count($query->getUsers()) > 0) {
            $params['users'] = array_values(array_map(fn ($u) => $u->getId(), $query->getUsers()));
        }
        if ($query->hasSearchTerm()) {
            $params['searchTerm'] = $query->getSearchTerm()->getOriginalSearch();
        }
        if ($query->getState() !== AbrechnungQuery::STATE_OPEN) {
            $params['state'] = $query->getState();
        }
        if ($query->getOrderBy() !== 'begin') {
            $params['orderBy'] = $query->getOrderBy();
        }
        if ($query->getOrder() !== AbrechnungQuery::ORDER_ASC) {
            $params['order'] = $query->getOrder();
        }
        if ($query->getPeriod() !== null) {
            $params['period'] = $query->getPeriod();
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function periodUrl(array $params, ?string $period): ?string
    {
        if ($period === null) {
            return null;
        }
        $params['period'] = $period;
        $params['performSearch'] = 1;

        return $this->generateUrl('abrechnung_index', $params);
    }

    /**
     * Filter parameters of the list, passed along in the URL of the bulk form, for the redirect without JS.
     *
     * @return array<string, mixed>
     */
    private function getRedirectParams(Request $request): array
    {
        $params = [];
        $period = AbrechnungQuery::normalizePeriod((string) $request->query->get('period', ''));
        if ($period !== null) {
            $params['period'] = $period;
        }
        foreach (['customers', 'users'] as $name) {
            $values = array_values(array_filter(array_map('intval', $request->query->all($name)), fn (int $id) => $id > 0));
            if (\count($values) > 0) {
                $params[$name] = $values;
            }
        }
        $state = $request->query->get('state');
        if (\in_array($state, [AbrechnungQuery::STATE_BILLED, AbrechnungQuery::STATE_ALL], true)) {
            $params['state'] = $state;
        }
        $searchTerm = $request->query->get('searchTerm');
        if (\is_string($searchTerm) && $searchTerm !== '') {
            $params['searchTerm'] = $searchTerm;
        }
        if (\count($params) > 0) {
            $params['performSearch'] = 1;
        }

        return $params;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getLegacyParams(Request $request): ?array
    {
        $q = $request->query;
        if (!$q->has('year') && !$q->has('month') && !$q->has('customer') && !$q->has('user')) {
            return null;
        }

        $params = ['performSearch' => 1];
        $year = filter_var($q->get('year'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1970, 'max_range' => 2999]]);
        $month = filter_var($q->get('month'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($year !== false && $month !== false) {
            $params['period'] = \sprintf('%04d-%02d', $year, $month);
        } elseif ($year !== false) {
            $params['period'] = (string) $year;
        }
        foreach (['customer' => 'customers', 'user' => 'users'] as $old => $new) {
            $id = filter_var($q->get($old), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $params[$new] = [$id];
            }
        }

        return $params;
    }
}
