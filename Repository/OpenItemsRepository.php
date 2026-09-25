<?php

namespace KimaiPlugin\AbrechnungBundle\Repository;

use App\Entity\Customer;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads open (billable, not exported, stopped) timesheets through Kimai's own
 * TimesheetRepository, so team permissions are applied exactly like on the
 * timesheet and export pages.
 */
class OpenItemsRepository
{
    public function __construct(
        private readonly TimesheetRepository $timesheetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Find all billable, unexported, completed timesheets visible to $currentUser.
     *
     * @return Timesheet[]
     */
    public function findOpenItems(
        User $currentUser,
        ?User $user = null,
        ?Customer $customer = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
    ): array {
        $query = new TimesheetQuery(false);
        $query->setCurrentUser($currentUser);
        $query->setBillable(true);
        $query->setExported(TimesheetQuery::STATE_NOT_EXPORTED);
        $query->setState(TimesheetQuery::STATE_STOPPED);
        $query->setOrderBy('begin');
        $query->setOrder(TimesheetQuery::ORDER_ASC);

        if ($user !== null) {
            $query->setUser($user);
        }

        if ($customer !== null) {
            $query->addCustomer($customer);
        }

        if ($dateFrom !== null) {
            $query->setBegin($dateFrom);
        }

        if ($dateTo !== null) {
            $query->setEnd($dateTo);
        }

        // Kimai batch-loads project, customer, activity and user of all rows (no N+1)
        return $this->timesheetRepository->getTimesheetsForQuery($query);
    }

    /**
     * Group open items by customer (sorted by customer name).
     *
     * @return array<int, array{customer: Customer, items: Timesheet[]}>
     */
    public function findGroupedByCustomer(
        User $currentUser,
        ?User $user = null,
        ?Customer $customer = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
    ): array {
        $groups = [];
        foreach ($this->findOpenItems($currentUser, $user, $customer, $dateFrom, $dateTo) as $item) {
            $itemCustomer = $item->getProject()->getCustomer();
            $customerId = $itemCustomer->getId();
            if (!isset($groups[$customerId])) {
                $groups[$customerId] = [
                    'customer' => $itemCustomer,
                    'items' => [],
                ];
            }
            $groups[$customerId]['items'][] = $item;
        }

        uasort($groups, fn (array $a, array $b) => strcasecmp((string) $a['customer']->getName(), (string) $b['customer']->getName()));

        return $groups;
    }

    /**
     * Year of the oldest open item (for the year filter), null if there is none.
     */
    public function findOldestOpenYear(): ?int
    {
        $min = $this->entityManager->createQueryBuilder()
            ->select('MIN(t.begin)')
            ->from(Timesheet::class, 't')
            ->where('t.billable = :billable')
            ->andWhere('t.exported = :exported')
            ->andWhere('t.end IS NOT NULL')
            ->setParameter('billable', true)
            ->setParameter('exported', false)
            ->getQuery()
            ->getSingleScalarResult();

        return $min === null ? null : (int) substr((string) $min, 0, 4);
    }
}
