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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace CmCIC\Form;

use CmCIC\CmCIC;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Core\Translation\Translator;

class ConfigureCmCIC extends BaseForm {
	public function getName() {
		return "configurecmcic";
	}

	protected function buildForm() {
        $values = null;
        $path = __DIR__."/../".CmCIC::JSON_CONFIG_PATH;
        if(is_readable($path)) {
            $values = json_decode(file_get_contents($path),true);
        }
		$this->formBuilder
			->add('com_key', 'text', array(
				'label' => Translator::getInstance()->trans('Merchant key'),
				'label_attr' => array(
					'for' => 'com_key'
				),
                'data' => (null === $values ?'':$values["CMCIC_KEY"]),
				'constraints' => array(
					new NotBlank()
				)
			))
			->add('TPE', 'text', array(
				'label' => Translator::getInstance()->trans('TPE'),
				'label_attr' => array(
					'for' => 'TPE'
				),
                'data' => (null === $values ?'':$values["CMCIC_TPE"]),
                'constraints' => array(
					new NotBlank()
				)
			))
			->add('com_soc', 'text', array(
				'label' => Translator::getInstance()->trans('Society code'),
				'label_attr' => array(
					'for' => 'com_soc'
				),
                'data' => (null === $values ?'':$values["CMCIC_CODESOCIETE"]),
                'constraints' => array(
					new NotBlank()
				)
			))
			->add('server', 'choice', array(
				'label' => Translator::getInstance()->trans('server'),
                'choices' => array(
                    "cic" => "CIC",
                    "cm" => "Crédit Mutuel",
                    "obc" => "OBC"
                ),
                'required' => 'true',
                'expanded'=>true,
                'multiple'=>false,
                'data' => (null === $values ?
                            '':
                            (preg_match("#cic-banques#i",$values["CMCIC_SERVER"])?
                                "cic":
                                (preg_match("#creditmutuel#i",$values["CMCIC_SERVER"])?
                                    "cm":
                                    (preg_match("#banque-obc#i",$values["CMCIC_SERVER"])?
                                        "obc":
                                        ""
                                    )
                                )
                            )
                          )
			))
			->add('page', 'text', array(
				'label' => Translator::getInstance()->trans('page'),
				'label_attr' => array(
					'for' => 'page'
				),
                'data' => (null === $values ?'':$values["CMCIC_PAGE"]),
				'constraints' => array(
					new NotBlank()
				)
			))
            ->add('debug', 'checkbox', array(
                'label' => "Debug",
                'label_attr' => array(
                    'for' => 'debug'
                ),
                'value' => (null !== $values && preg_match("#test#i", $values["CMCIC_SERVER"]) ?"true":""),
            ))
		;
	}
}
?>
