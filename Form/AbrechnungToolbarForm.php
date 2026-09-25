<?php

namespace KimaiPlugin\AbrechnungBundle\Form;

use App\Form\Toolbar\ToolbarFormTrait;
use App\Form\Type\CustomerType;
use KimaiPlugin\AbrechnungBundle\Repository\Query\AbrechnungQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Toolbar (search dropdown) of the billing page, rendered by Kimai's
 * macros/datatables.html.twig "actions" macro like every core list page.
 *
 * Customer and user choices are team scoped by Kimai's CustomerType/UserType
 * (only customers and active users the current user may see).
 *
 * @extends AbstractType<AbrechnungQuery>
 */
final class AbrechnungToolbarForm extends AbstractType
{
    use ToolbarFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addSearchTermInputField($builder);
        $builder->add('customers', CustomerType::class, [
            'multiple' => true,
            'required' => false,
        ]);
        $this->addUsersChoice($builder);
        $builder->add('state', ChoiceType::class, [
            'label' => 'abrechnung.state',
            'search' => false,
            'choices' => [
                'abrechnung.state_open' => AbrechnungQuery::STATE_OPEN,
                'abrechnung.state_billed' => AbrechnungQuery::STATE_BILLED,
                'abrechnung.state_all' => AbrechnungQuery::STATE_ALL,
            ],
        ]);
        // set by the period navigator (kit.period_nav), kept when the filter is submitted
        $builder->add('period', HiddenType::class, [
            'required' => false,
        ]);
        $this->addHiddenPagination($builder);
        $this->addOrder($builder);
        $this->addOrderBy($builder, AbrechnungQuery::ORDER_ALLOWED);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AbrechnungQuery::class,
            'csrf_protection' => false,
        ]);
    }
}
