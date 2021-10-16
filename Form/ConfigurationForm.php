<?php

namespace Scalapay\Form;

use Scalapay\Scalapay;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                Scalapay::MODE,
                ChoiceType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'choices' => [
                        'TEST' => 'Test',
                        'PRODUCTION' => 'Production',
                    ],
                    'label' => $this->trans('Mode de fonctionnement'),
                    'data' => Scalapay::getConfigValue(Scalapay::MODE),
                ]
            )
            ->add(
                Scalapay::ACCESS_KEY,
                TextType::class,
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('Access Key'),
                    'data' => Scalapay::getConfigValue(Scalapay::ACCESS_KEY, ''),
                ]
            )
            ->add(
                Scalapay::ALLOWED_IP_LIST,
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->trans('Allowed IPs in test mode'),
                    'data' => Scalapay::getConfigValue(Scalapay::ALLOWED_IP_LIST),
                    'label_attr' => array(
                        'for' => Scalapay::ALLOWED_IP_LIST,
                        'help' => $this->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            array('%ip' => $this->getRequest()->getClientIp())
                        ),
                        'rows' => 3
                    )
                ]
            )
            ->add(
                Scalapay::MINIMUM_AMOUNT,
                NumberType::class,
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total'),
                    'data' => Scalapay::getConfigValue(Scalapay::MINIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Scalapay::MAXIMUM_AMOUNT,
                NumberType::class,
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total'),
                    'data' => Scalapay::getConfigValue(Scalapay::MAXIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
        ;
    }

    public function getName()
    {
        return 'scalapay_configuration';
    }

    protected function trans($str, $params = [])
    {
        return Translator::getInstance()->trans($str, $params, Scalapay::DOMAIN_NAME);
    }
}
