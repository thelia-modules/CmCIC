<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CmCIC\Form;

use CmCIC\CmCIC;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\ModuleConfigQuery;

class ConfigureCmCIC extends BaseForm
{
    protected function buildForm()
    {
        $values = [];
        foreach (ModuleConfigQuery::create()->filterByModuleId(CmCIC::getModuleId())->find()->getData() as $moduleConf) {
            $values[$moduleConf->getName()] = $moduleConf->getValue();
        }


        $this->formBuilder
            ->add('CMCIC_KEY', TextType::class, [
                'label' => Translator::getInstance()->trans('Merchant key', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'CMCIC_KEY'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_KEY"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('CMCIC_TPE', TextType::class, [
                'label' => Translator::getInstance()->trans('TPE', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'CMCIC_TPE'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_TPE"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('CMCIC_CODESOCIETE', TextType::class, [
                'label' => Translator::getInstance()->trans('Society code', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'CMCIC_CODESOCIETE'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_CODESOCIETE"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('CMCIC_SERVER', ChoiceType::class, [
                'label' => Translator::getInstance()->trans('server', [], CmCIC::DOMAIN_NAME),
                'choices' => [
                    "CIC" => "cic",
                    "Crédit Mutuel" => "cm",
                    "OBC" => "obc",
                    "MONETICO" => "mon"
                ],
                'required' => 'true',
                'expanded' => true,
                'multiple' => false,
                'data' => (null === $values ?
                    '' :
                    (preg_match("#cic-banques#i", $values["CMCIC_SERVER"]) ?
                        "cic" :
                        (preg_match("#creditmutuel#i", $values["CMCIC_SERVER"]) ?
                            "cm" :
                            (preg_match("#banque-obc#i", $values["CMCIC_SERVER"]) ?
                                "obc" :
                                (preg_match("#monetico#i", $values["CMCIC_SERVER"]) ?
                                    "mon" :
                                    ""
                                )
                            )
                        )
                    )
                ),
                'label_attr' => [
                    'help' => Translator::getInstance()->trans(
                        "The module may be used with several banks. Please select your bank here.",
                        [],
                        CmCIC::DOMAIN_NAME
                    )
                ],

            ])
            ->add('CMCIC_PAGE', TextType::class, [
                'label' => Translator::getInstance()->trans('page', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'help' => Translator::getInstance()->trans(
                        "The page invoked on the payment server. The default should be OK in most cases.",
                        [],
                        CmCIC::DOMAIN_NAME
                    )
                ],
                'data' => (null === $values ? '' : $values["CMCIC_PAGE"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('CMCIC_DEBUG', CheckboxType::class, [
                'label' => Translator::getInstance()->trans('Run in test mode', [], CmCIC::DOMAIN_NAME),
                'required' => false,
                'label_attr' => [
                    'for' => 'CMCIC_DEBUG',
                    'help' => Translator::getInstance()->trans(
                        "Check this box to test the payment system, using test credit cards to simulate various situations.",
                        [],
                        CmCIC::DOMAIN_NAME
                    )
                ],
                'data' => !empty($values['CMCIC_DEBUG'])
            ])
            ->add(
                'CMCIC_ALLOWED_IPS',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans('Allowed IPs in test mode', [], CmCIC::DOMAIN_NAME),
                    'data' => $values['CMCIC_ALLOWED_IPS'] ?? "",
                    'label_attr' => [
                        'for' => 'CMCIC_ALLOWED_IPS',
                        'help' => Translator::getInstance()->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            ['%ip' => $this->getRequest()->getClientIp()],
                            CmCIC::DOMAIN_NAME
                        ),
                        'rows' => 3
                    ]
                ]
            )->add(
                'CMCIC_send_confirmation_message_only_if_paid',
                CheckboxType::class,
                [
                    'value' => 1,
                    'data' => !empty($values['CMCIC_send_confirmation_message_only_if_paid']),
                    'required' => false,
                    'label' => $this->translator->trans('Send order confirmation on payment success', [], CmCIC::DOMAIN_NAME),
                    'label_attr' => [
                        'help' => $this->translator->trans(
                            'If checked, the order confirmation message is sent to the customer only when the payment is successful. The order notification is always sent to the shop administrator',
                            [],
                            CmCIC::DOMAIN_NAME
                        )
                    ]
                ]
            );
    }
}
