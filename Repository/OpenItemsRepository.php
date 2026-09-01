<?php

namespace KimaiPlugin\AbrechnungBundle\Repository;

use App\Entity\Customer;
use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Timesheet>
 */
class OpenItemsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Timesheet::class);
    }

    /**
     * Find all billable, unexported, completed timesheets.
     *
     * @return Timesheet[]
     */
    public function findOpenItems(
        ?User $user = null,
        ?Customer $customer = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->leftJoin('p.customer', 'c')
            ->leftJoin('t.activity', 'a')
            ->leftJoin('t.user', 'u')
            ->where('t.billable = :billable')
            ->andWhere('t.exported = :exported')
            ->andWhere('t.end IS NOT NULL')
            ->setParameter('billable', true)
            ->setParameter('exported', false)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('t.begin', 'ASC');

        if ($customer !== null) {
            $qb->andWhere('c.id = :customerId')
               ->setParameter('customerId', $customer->getId());
        }

        if ($user !== null) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', $user->getId());
        }

        if ($dateFrom !== null) {
            $qb->andWhere('t.begin >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo !== null) {
            $qb->andWhere('t.begin <= :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Group open items by customer.
     *
     * @return array<int, array{customer: Customer, items: Timesheet[], totalDuration: int, totalRate: float}>
     */
    public function findGroupedByCustomer(
        ?User $user = null,
        ?Customer $customer = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null,
    ): array {
        $items = $this->findOpenItems($user, $customer, $dateFrom, $dateTo);

        $groups = [];
        foreach ($items as $item) {
            $customer = $item->getProject()->getCustomer();
            $customerId = $customer->getId();
            if (!isset($groups[$customerId])) {
                $groups[$customerId] = [
                    'customer' => $customer,
                    'items' => [],
                    'totalDuration' => 0,
                    'totalRate' => 0.0,
                ];
            }
            $groups[$customerId]['items'][] = $item;
            $groups[$customerId]['totalDuration'] += $item->getDuration();
            $groups[$customerId]['totalRate'] += $item->getRate() ?? 0.0;
        }

        return $groups;
    }

    /**
     * Count open billable items.
     */
    public function countOpenItems(): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.billable = :billable')
            ->andWhere('t.exported = :exported')
            ->andWhere('t.end IS NOT NULL')
            ->setParameter('billable', true)
            ->setParameter('exported', false);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
