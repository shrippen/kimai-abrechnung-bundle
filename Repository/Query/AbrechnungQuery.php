<?php

namespace KimaiPlugin\AbrechnungBundle\Repository\Query;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\Query\BaseQuery;

/**
 * Filter of the billing page (toolbar search form).
 *
 * The period is stored as "YYYY-MM" (month) or "YYYY" (year); null means "all open entries".
 * Every field that is visible in the toolbar is registered as default, so Kimai counts
 * active filters (badge + reset link) exactly like on its own list pages.
 */
class AbrechnungQuery extends BaseQuery
{
    public const STATE_OPEN = 'open';
    public const STATE_BILLED = 'billed';
    public const STATE_ALL = 'all';

    public const ORDER_ALLOWED = ['begin', 'duration', 'rate', 'user', 'description'];

    /** @var array<Customer> */
    private array $customers = [];
    /** @var array<int, User> */
    private array $users = [];
    private ?string $period = null;
    private string $state = self::STATE_OPEN;

    public function __construct()
    {
        $this->setDefaults([
            'orderBy' => 'begin',
            'order' => self::ORDER_ASC,
            'customers' => [],
            'users' => [],
            'period' => null,
            'state' => self::STATE_OPEN,
        ]);
        $this->setAllowedOrderColumns(self::ORDER_ALLOWED);
    }

    /**
     * @return array<Customer>
     */
    public function getCustomers(): array
    {
        return $this->customers;
    }

    /**
     * @param iterable<Customer>|null $customers
     */
    public function setCustomers(?iterable $customers): void
    {
        $this->customers = [];
        foreach ($customers ?? [] as $customer) {
            $this->addCustomer($customer);
        }
    }

    public function addCustomer(Customer $customer): void
    {
        $this->customers[] = $customer;
    }

    /**
     * @return array<User>
     */
    public function getUsers(): array
    {
        return array_values($this->users);
    }

    /**
     * @param iterable<User>|null $users
     */
    public function setUsers(?iterable $users): void
    {
        $this->users = [];
        foreach ($users ?? [] as $user) {
            $this->addUser($user);
        }
    }

    public function addUser(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    /**
     * Accepts "YYYY-MM" or "YYYY", everything else resets to "all" (no error page for hand-written URLs).
     */
    public function setPeriod(?string $period): void
    {
        $this->period = self::normalizePeriod($period);
    }

    public static function normalizePeriod(?string $period): ?string
    {
        if ($period === null || $period === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) === 1) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($year >= 1970 && $year <= 2999 && $month >= 1 && $month <= 12) {
                return \sprintf('%04d-%02d', $year, $month);
            }

            return null;
        }

        if (preg_match('/^\d{4}$/', $period) === 1) {
            $year = (int) $period;

            return ($year >= 1970 && $year <= 2999) ? (string) $year : null;
        }

        return null;
    }

    /**
     * @return 'month'|'year'|null
     */
    public function getPeriodUnit(): ?string
    {
        if ($this->period === null) {
            return null;
        }

        return \strlen($this->period) === 4 ? 'year' : 'month';
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = \in_array($state, [self::STATE_OPEN, self::STATE_BILLED, self::STATE_ALL], true) ? $state : self::STATE_OPEN;
    }
}
