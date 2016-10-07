 _____           _____ _____ _____                       _       _
/  __ \         /  __ \_   _/  __ \                     | |     | |
| /  \/_ __ ___ | /  \/ | | | /  \/  _ __ ___   ___   __| |_   _| | ___
| |   | '_ ` _ \| |     | | | |     | '_ ` _ \ / _ \ / _` | | | | |/ _ \
| \__/\ | | | | | \__/\_| |_| \__/\ | | | | | | (_) | (_| | |_| | |  __/
 \____/_| |_| |_|\____/\___/ \____/ |_| |_| |_|\___/ \__,_|\__,_|_|\___|
                             ______         _____ _          _ _
                             | ___ \       |_   _| |        | (_)
                             | |_/ /_   _    | | | |__   ___| |_  __ _
                             | ___ \ | | |   | | | '_ \ / _ \ | |/ _` |
                             | |_/ / |_| |   | | | | | |  __/ | | (_| |
                             \____/ \__, |   \_/ |_| |_|\___|_|_|\__,_|
                                     __/ |
                                    |___/   <info@thelia.net>


SUMMARY
-------

fr_FR:
0.  Pré-requis
1.  Installation
2.  Utilisation

en_US:
0.  Prerequisites
1.  Install notes
2.  How to use

fr_FR
-----

### Pré-requis

Quand vous passez un contrat avec le CmCIC, vous devez donner une "URL de retour" pour votre site.
Cette adresse est formée de la manière suivante: http://www.votresite.com/cmcic/validation
Par exemple, pour le site thelia.net, l'adresse serait: http://www.thelia.net/cmcic/validation

### Installation

Pour installer le module cmcic, téléchargez l'archive et décompressez la dans <dossier de thelia>/local/modules

### Utilisation

Pour utiliser le module cmcic, allez dans le back-office, onglet Modules, et activez le,
puis cliquez sur "Configurer" sur la ligne du module. Renseignez vos informations de commerçant.
Le champ page n'a normalement pas besoin d'être changé, et sa valeur par défault est: paiement.cgi
La case "Mode Test" permet, quand elle est cochée, de faire fonctionner le module en mode test, c'est à dire, de passer
des commandes à blanc avec des numéros de carte bancaire de test.

en_US
-----

### Prerequisites

When you sign a contract with CmCIC, you have to give them a "Return URL" for your website.
This address is built  like this: http://www.yoursite.com/cmcic/validation
For example, for the website thelia.net, the address would be: http://www.thelia.net/cmcic/validation

### Install notes

To install the cmcic module, download the archive uncompress it in <path to thelia>/local/modules

### How to use

To use this module, your first need to activate if in the Back-office, tab Modules,
then click on "Configure" on the cmcic module line. Enter your cmcic account information and save.
Then entry "page" is normally set as "paiement.cgi".
The checkbox "Test Mode" allows you, when it is checked, to do fake orders with test credit cards.
