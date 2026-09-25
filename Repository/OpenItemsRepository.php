<?php

namespace KimaiPlugin\AbrechnungBundle\Repository;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use KimaiPlugin\AbrechnungBundle\Repository\Query\AbrechnungQuery;

/**
 * Loads billable, stopped timesheets (open = not exported, billed = exported) through Kimai's own
 * TimesheetRepository, so team permissions are applied exactly like on the timesheet and export pages.
 */
class OpenItemsRepository
{
    public function __construct(
        private readonly TimesheetRepository $timesheetRepository,
    ) {
    }

    /**
     * @return Timesheet[]
     */
    public function findItems(
        User $currentUser,
        AbrechnungQuery $filter,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
    ): array {
        $query = new TimesheetQuery(false);
        $query->setCurrentUser($currentUser);
        $query->setBillable(true);
        $query->setState(TimesheetQuery::STATE_STOPPED);
        $query->setExported(match ($filter->getState()) {
            AbrechnungQuery::STATE_BILLED => TimesheetQuery::STATE_EXPORTED,
            AbrechnungQuery::STATE_ALL => TimesheetQuery::STATE_ALL,
            default => TimesheetQuery::STATE_NOT_EXPORTED,
        });

        $orderBy = $filter->getOrderBy();
        if ($orderBy === 'user') {
            // TimesheetQuery has no user order; the page sorts by user within each project instead
            $orderBy = 'begin';
        }
        $query->setOrderBy(\in_array($orderBy, TimesheetQuery::TIMESHEET_ORDER_ALLOWED, true) ? $orderBy : 'begin');
        $query->setOrder($filter->getOrder());

        if ($filter->hasSearchTerm()) {
            $query->setSearchTerm($filter->getSearchTerm());
        }

        foreach ($filter->getUsers() as $user) {
            $query->addUser($user);
        }

        foreach ($filter->getCustomers() as $customer) {
            $query->addCustomer($customer);
        }

        if ($dateFrom !== null) {
            $query->setBegin($dateFrom);
        }

        if ($dateTo !== null) {
            $query->setEnd($dateTo);
        }

        // Kimai batch-loads project, customer, activity and user of all rows (no N+1)
        $items = $this->timesheetRepository->getTimesheetsForQuery($query);

        if ($filter->getOrderBy() === 'user') {
            $direction = $filter->getOrder() === AbrechnungQuery::ORDER_DESC ? -1 : 1;
            usort($items, fn (Timesheet $a, Timesheet $b) => $direction * (strcasecmp((string) $a->getUser()?->getDisplayName(), (string) $b->getUser()?->getDisplayName()) ?: ($a->getBegin() <=> $b->getBegin())));
        }

        return $items;
    }
}
