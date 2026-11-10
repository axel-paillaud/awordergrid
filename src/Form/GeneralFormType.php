<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */
declare(strict_types=1);

namespace Axelweb\AwOrderGrid\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GeneralFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sample_config', TextType::class, [
                'label' => $this->trans('Sample configuration', 'Modules.Awordergrid.Admin'),
                'help' => $this->trans('Example configuration field', 'Modules.Awordergrid.Admin'),
                'required' => false,
                'constraints' => [
                    new Assert\Length(['max' => 255]),
                ],
                'attr' => [
                    'placeholder' => 'Example value',
                    'autocomplete' => 'off',
                ],
            ]);
    }
}
