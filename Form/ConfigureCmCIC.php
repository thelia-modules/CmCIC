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
use JsonException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigureCmCIC extends BaseForm
{
    /**
     * @throws JsonException
     */
    protected function buildForm(): void
    {
        $values = null;
        $path = __DIR__ . "/../" . CmCIC::JSON_CONFIG_PATH;
        if (is_readable($path)) {
            $values = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        $this->formBuilder
            ->add('com_key', TextType::class, [
                'label' => Translator::getInstance()->trans('Merchant key', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'com_key'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_KEY"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('TPE', TextType::class, [
                'label' => Translator::getInstance()->trans('TPE', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'TPE'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_TPE"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('com_soc', TextType::class, [
                'label' => Translator::getInstance()->trans('Society code', [], CmCIC::DOMAIN_NAME),
                'label_attr' => [
                    'for' => 'com_soc'
                ],
                'data' => (null === $values ? '' : $values["CMCIC_CODESOCIETE"]),
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('server', ChoiceType::class, [
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
                'data' => (
                    null === $values ?
                    '' :
                    (
                        preg_match("#cic-banques#i", $values["CMCIC_SERVER"]) ?
                        "cic" :
                        (
                            preg_match("#creditmutuel#i", $values["CMCIC_SERVER"]) ?
                            "cm" :
                            (
                                preg_match("#banque-obc#i", $values["CMCIC_SERVER"]) ?
                                "obc" :
                                (
                                    preg_match("#monetico#i", $values["CMCIC_SERVER"]) ?
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
            ->add('page', TextType::class, [
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
            ->add('debug', CheckboxType::class, [
                'label' => Translator::getInstance()->trans('Run in test mode', [], CmCIC::DOMAIN_NAME),
                'required' => false,
                'label_attr' => [
                    'for' => 'debug',
                    'help' => Translator::getInstance()->trans(
                        "Check this box to test the payment system, using test credit cards to simulate various situations.",
                        [],
                        CmCIC::DOMAIN_NAME
                    )
                ],
                'data' => (bool)CmCIC::getConfigValue('debug', false)
            ])
            ->add(
                'allowed_ips',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans('Allowed IPs in test mode', [], CmCIC::DOMAIN_NAME),
                    'data' => CmCIC::getConfigValue('allowed_ips', ''),
                    'label_attr' => [
                        'for' => 'allowed_ips',
                        'help' => Translator::getInstance()->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            ['%ip' => $this->getRequest()->getClientIp()],
                            CmCIC::DOMAIN_NAME
                        ),
                        'rows' => 3
                    ]
                ]
            )
            ->add(
                'send_confirmation_message_only_if_paid',
                CheckboxType::class,
                [
                    'value' => 1,
                    'data' => !empty(CmCIC::getConfigValue('send_confirmation_message_only_if_paid', '')),
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
            )
        ;
    }
}
