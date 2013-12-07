<?php

/**
 * @file SemanticACL.i18n.php
 * @ingroup SemanticACL
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$messages = array();

/** English
 * @author Werdna
 */
$messages['en'] = array(
	'sacl-desc' => 'Allows access restrictions to be set with Semantic MediaWiki properties',
	'sacl-denied' => 'You are not on the access list for this page.',
	'right-sacl-exempt' => 'Exempt from Semantic ACLs',
	
	'sacl-property-visibility' => 'Visible to',
	'sacl-property-visibility-wl-group' => 'Visible to group',
	'sacl-property-visibility-wl-user' => 'Visible to user',
	
	'sacl-property-editable' => 'Editable by',
	'sacl-property-editable-wl-group' => 'Editable by group',
	'sacl-property-editable-wl-user' => 'Editable by user',
);

/** Message documentation (Message documentation)
 * @author Kghbln
 * @author Shirayuki
 * @author Umherirrender
 */
$messages['qqq'] = array(
	'sacl-desc' => '{{desc|name=Semantic ACL|url=http://www.mediawiki.org/wiki/Extension:SemanticACL}}',
	'sacl-denied' => 'This is an informatory message.',
	'right-sacl-exempt' => '{{doc-right|sacl-exempt}}
ACL = Access Control List',
	'sacl-property-visibility' => 'This is the name of the property that will hold the values defining the user(s) or user group(s) which may view a page.

See also:
* {{msg-mw|Sacl-property-visibility-wl-group}}
* {{msg-mw|Sacl-property-visibility-wl-user}}',
	'sacl-property-visibility-wl-group' => 'This is the name of the property that will hold the values defining the user group(s) which may view a page.

See also:
* {{msg-mw|Sacl-property-visibility}}
* {{msg-mw|Sacl-property-visibility-wl-user}}',
	'sacl-property-visibility-wl-user' => 'This is the name of the property that will hold the values defining the user(s) which may view a page.

See also:
* {{msg-mw|Sacl-property-visibility}}
* {{msg-mw|Sacl-property-visibility-wl-group}}',
	'sacl-property-editable' => 'This is the name of the property that will hold the values defining the user(s) or user group(s) which may edit a page.

See also:
* {{msg-mw|Sacl-property-editable-wl-group}}
* {{msg-mw|Sacl-property-editable-wl-user}}',
	'sacl-property-editable-wl-group' => 'This is the name of the property that will hold the values defining the user group(s) which may edit a page.

See also:
* {{msg-mw|Sacl-property-editable}}
* {{msg-mw|Sacl-property-editable-wl-user}}',
	'sacl-property-editable-wl-user' => 'This is the name of the property that will hold the values defining the user(s) which may edit a page.

See also:
* {{msg-mw|Sacl-property-editable}}
* {{msg-mw|Sacl-property-editable-wl-group}}',
);

/** Asturian (asturianu)
 * @author Xuacu
 */
$messages['ast'] = array(
	'sacl-desc' => "Permite definir torgues d'accesu coles propiedaes de Semantic MediaWiki",
	'sacl-denied' => "Nun tas na llista d'accesu d'esta páxina.",
	'right-sacl-exempt' => "Dispensáu de les llistes de control d'accesu semántiques",
	'sacl-property-visibility' => 'Visible pa',
	'sacl-property-visibility-wl-group' => 'Visible pal grupu',
	'sacl-property-visibility-wl-user' => 'Visible pal usuariu',
	'sacl-property-editable' => 'Editable por',
	'sacl-property-editable-wl-group' => 'Editable pol grupu',
	'sacl-property-editable-wl-user' => 'Editable pol usuariu',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'sacl-desc' => 'Дазваляе ладзіць абмежаваньні доступу праз уласьцівасьці Semantic MediaWiki',
	'sacl-denied' => 'Вы ня ўключаныя ў сьпіс доступу да гэтай старонкі.',
	'right-sacl-exempt' => 'выключэньне са сэмантычных сьпісаў доступу',
	'sacl-property-visibility' => 'Бачны',
	'sacl-property-visibility-wl-group' => 'Бачны групе',
	'sacl-property-visibility-wl-user' => 'Бачны ўдзельніку',
	'sacl-property-editable' => 'Можа рэдагавацца',
	'sacl-property-editable-wl-group' => 'Можа рэдагавацца групай',
	'sacl-property-editable-wl-user' => 'Можа рэдагавацца ўдзельнікам',
);

/** Breton (brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'sacl-property-visibility' => "A c'hall bezañ gwelet gant",
	'sacl-property-visibility-wl-group' => "A c'hall bezañ gwelet gant ar strolladoù",
	'sacl-property-visibility-wl-user' => "A c'hall bezañ gwelet gant an implijer",
	'sacl-property-editable' => "A c'hall bezañ kemmet gant",
	'sacl-property-editable-wl-group' => "A c'hall bezañ kemmet gant ar strollad",
	'sacl-property-editable-wl-user' => "A c'hall bezañ kemmet gant an implijer",
);

/** Czech (čeština)
 * @author Vks
 */
$messages['cs'] = array(
	'sacl-property-visibility' => 'Viditelné',
	'sacl-property-visibility-wl-group' => 'Viditelné skupině',
	'sacl-property-visibility-wl-user' => 'Viditelné uživateli',
	'sacl-property-editable' => 'Upravovatelné',
	'sacl-property-editable-wl-group' => 'Upravovatelné skupinou',
	'sacl-property-editable-wl-user' => 'Upravovatelné uživatelem',
);

/** German (Deutsch)
 * @author Kghbln
 */
$messages['de'] = array(
	'sacl-desc' => 'Ermöglicht mit semantischen Attributen verknüpfte Zugriffsbeschränkungen auf Seiten',
	'sacl-denied' => 'Du befindest dich nicht auf der Liste der für diese Seite Zugriffsberechtigten.',
	'right-sacl-exempt' => 'Ausgenommen von Zugriffsbeschränkungen auf semantische Attribute',
	'sacl-property-visibility' => 'Sichtbar für',
	'sacl-property-visibility-wl-group' => 'Sichtbar für Benutzergruppe',
	'sacl-property-visibility-wl-user' => 'Sichtbar für Benutzer',
	'sacl-property-editable' => 'Bearbeitbar von',
	'sacl-property-editable-wl-group' => 'Bearbeitbar von Benutzergruppe',
	'sacl-property-editable-wl-user' => 'Bearbeitbar von Benutzer',
);

/** Spanish (español)
 * @author Armando-Martin
 */
$messages['es'] = array(
	'sacl-desc' => 'Permite definir restricciones de acceso con las propiedades de Semantic MediaWiki',
	'sacl-denied' => 'No está en la lista de acceso de esta página.',
	'right-sacl-exempt' => 'Exento de la lista de control de acceso de Semantic',
	'sacl-property-visibility' => 'Visible para',
	'sacl-property-visibility-wl-group' => 'Visible para el grupo',
	'sacl-property-visibility-wl-user' => 'Visible para el usuario',
	'sacl-property-editable' => 'Editable por',
	'sacl-property-editable-wl-group' => 'Editable por el grupo',
	'sacl-property-editable-wl-user' => 'Editable por el usuario',
);

/** Persian (فارسی)
 * @author Ebraminio
 */
$messages['fa'] = array(
	'sacl-desc' => 'اجازه می‌دهد محدودیت‌های دسترسی با مشخصات معنایی مدیاویکی تنظیم شوند',
	'sacl-denied' => 'شما در فهرست دسترسی به این صفحه نیستید.',
	'right-sacl-exempt' => 'معاف از فهرست کنترل دسترسی (ACL) معنایی',
	'sacl-property-visibility' => 'قابل نمایش برای',
	'sacl-property-visibility-wl-group' => 'قابل نمایش برای گروه',
	'sacl-property-visibility-wl-user' => 'قابل نمایش برای کاربر',
	'sacl-property-editable' => 'قابل ویرایش برای',
	'sacl-property-editable-wl-group' => 'قابل ویرایش برای گروه',
	'sacl-property-editable-wl-user' => 'قابل ویرایش برای کاربر',
);

/** Finnish (suomi)
 * @author Nedergard
 */
$messages['fi'] = array(
	'sacl-desc' => 'Sallii Semantic Media-Wikin ominaisuuksien käyttörajoitusten asettamisen.',
	'sacl-denied' => 'Käyttäjätunnuksesi ei ole tämän sivun käyttöoikeusluettelossa.',
	'right-sacl-exempt' => 'Ei kuulu semanttiseen käyttörajoitusluetteloon',
	'sacl-property-visibility' => 'Näyttöoikeudet',
	'sacl-property-visibility-wl-group' => 'Näkyy ryhmälle',
	'sacl-property-visibility-wl-user' => 'Näkyy käyttäjälle',
	'sacl-property-editable' => 'Muokkausoikeudet',
	'sacl-property-editable-wl-group' => 'Muokkausoikeudet ryhmällä',
	'sacl-property-editable-wl-user' => 'Muokkausoikeudet käyttäjällä',
);

/** French (français)
 * @author Sherbrooke
 */
$messages['fr'] = array(
	'sacl-desc' => "Gère les restrictions d'accès ''via'' des propriétés de Semantic MediaWiki",
	'sacl-denied' => "Vous n'êtes pas sur la liste d'accès pour cette page.",
	'right-sacl-exempt' => "Exempté de la liste d'accès de Semantic MediaWiki",
	'sacl-property-visibility' => 'Visible à',
	'sacl-property-visibility-wl-group' => 'Visible pour les groupes',
	'sacl-property-visibility-wl-user' => "Visible pour l'utilisateur",
	'sacl-property-editable' => 'Modifiable par',
	'sacl-property-editable-wl-group' => 'Modifiable par le groupe',
	'sacl-property-editable-wl-user' => "Modifiable par l'utilisateur",
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'sacl-property-visibility' => 'Visiblo por',
	'sacl-property-visibility-wl-group' => 'Visiblo por la tropa',
	'sacl-property-visibility-wl-user' => 'Visiblo por l’usanciér',
	'sacl-property-editable' => 'Chanjâblo por',
	'sacl-property-editable-wl-group' => 'Chanjâblo por la tropa',
	'sacl-property-editable-wl-user' => 'Chanjâblo por l’usanciér',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'sacl-desc' => 'Permite definir restricións de acceso coas propiedades de Semantic MediaWiki',
	'sacl-denied' => 'Non está na lista de acceso desta páxina.',
	'right-sacl-exempt' => 'Exento da lista de control de acceso semántica',
	'sacl-property-visibility' => 'Visible para',
	'sacl-property-visibility-wl-group' => 'Visible para o grupo',
	'sacl-property-visibility-wl-user' => 'Visible para o usuario',
	'sacl-property-editable' => 'Editable por',
	'sacl-property-editable-wl-group' => 'Editable polo grupo',
	'sacl-property-editable-wl-user' => 'Editable polo usuario',
);

/** Hebrew (עברית)
 * @author Amire80
 */
$messages['he'] = array(
	'sacl-desc' => 'הוספת אפשרות להגדיר הגבלות על מאפיינים של מדיה־ויקי סמנטית',
	'sacl-denied' => 'אינך מופיע ברשימת הגישה לדף הזה.',
	'right-sacl-exempt' => 'פטור מרשימות בקרת גישה סמנטיות',
	'sacl-property-visibility' => 'גלוי ל',
	'sacl-property-visibility-wl-group' => 'גלוי לקבוצה',
	'sacl-property-visibility-wl-user' => 'גלוי למשתמש',
	'sacl-property-editable' => 'ניתן לעריכה על־ידי',
	'sacl-property-editable-wl-group' => 'ניתן לעריכה על־ידי קבוצה',
	'sacl-property-editable-wl-user' => 'ניתן לעריכה על־ידי המשתמש',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'sacl-desc' => 'Zmóžnja přistupne wobmjezowanja zwjazane z atributami Semantic MediaWiki',
	'sacl-denied' => 'Njejsy na lisćinje woprawnjenych za přistup na tutu stronu.',
	'right-sacl-exempt' => 'Ze Semantic ACL wuwzać',
	'sacl-property-visibility' => 'Widźomny za',
	'sacl-property-visibility-wl-group' => 'Widźomny za skupinu',
	'sacl-property-visibility-wl-user' => 'Widźomny za wužiwarja',
	'sacl-property-editable' => 'Wobdźěłujomny wot',
	'sacl-property-editable-wl-group' => 'Wobdźěłujomny wot skupiny',
	'sacl-property-editable-wl-user' => 'Wobdźěłujomny wot wužiwarja',
);

/** Hungarian (magyar)
 * @author TK-999
 */
$messages['hu'] = array(
	'sacl-desc' => 'Lehetvőé teszi a hozzáférés korlátozásának beállítását szemantikus MediaWiki-tulajdonságokkal',
	'sacl-denied' => 'Nem vagy rajta ezen lap hozzáférési listáján.',
	'right-sacl-exempt' => 'Szemantikus hozzáférés-szabályozó listák alól független',
	'sacl-property-visibility' => 'Megtekinthetik',
	'sacl-property-visibility-wl-group' => 'Megtekintheti az alábbi csoport',
	'sacl-property-visibility-wl-user' => 'Megtekintheti az alábbi felhasználó',
	'sacl-property-editable' => 'Szerkesztheti',
	'sacl-property-editable-wl-group' => 'Szerkesztheti az alábbi csoport',
	'sacl-property-editable-wl-user' => 'Szerkesztheti az alábbi felhasználó',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'sacl-desc' => 'Permitte definir restrictiones de accesso con proprietates de Semantic MediaWiki',
	'sacl-denied' => 'Tu non es in le lista de accesso de iste pagina.',
	'right-sacl-exempt' => 'Exempte del controlo de accesso de Semantic MediaWiki',
	'sacl-property-visibility' => 'Visibile pro',
	'sacl-property-visibility-wl-group' => 'Visibile pro le gruppo',
	'sacl-property-visibility-wl-user' => 'Visibile pro le usator',
	'sacl-property-editable' => 'Modificabile per',
	'sacl-property-editable-wl-group' => 'Modificabile per le gruppo',
	'sacl-property-editable-wl-user' => 'Modificabile per le usator',
);

/** Italian (italiano)
 * @author Beta16
 */
$messages['it'] = array(
	'sacl-desc' => 'Permette di impostare restrizioni di accesso con proprietà Semantic MediaWiki',
	'sacl-denied' => 'Non sei sulla lista di accesso per questa pagina.',
	'right-sacl-exempt' => 'Esente da ACL (Access Control List) semantico',
	'sacl-property-visibility' => 'Visibile a',
	'sacl-property-visibility-wl-group' => 'Visibile al gruppo',
	'sacl-property-visibility-wl-user' => "Visibile all'utente",
	'sacl-property-editable' => 'Modificabile da',
	'sacl-property-editable-wl-group' => 'Modificabile dal gruppo',
	'sacl-property-editable-wl-user' => "Modificabile dall'utente",
);

/** Japanese (日本語)
 * @author Schu
 * @author Shirayuki
 */
$messages['ja'] = array(
	'sacl-desc' => 'Semantic MediaWiki のプロパティでアクセス制限を設定できるようにする',
	'sacl-denied' => 'あなたは、このページのアクセスリストに含まれていません。',
	'right-sacl-exempt' => '意味的 ACL から免除',
	'sacl-property-visibility' => '閲覧可能',
	'sacl-property-visibility-wl-group' => 'グループが閲覧可能',
	'sacl-property-visibility-wl-user' => '利用者が閲覧可能',
	'sacl-property-editable' => '編集可能',
	'sacl-property-editable-wl-group' => 'グループが編集可能',
	'sacl-property-editable-wl-user' => '利用者が編集可能',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'sacl-desc' => 'Määd_et möjjelesch, Zohjreffsrääschde noh semntesche Eijeschaffte ze verjävve.',
	'sacl-denied' => 'Doh bes nit op dä Zohjreffsleß för heh di Sigg.',
	'right-sacl-exempt' => 'Ußjenomme vun de semantesche Zohjreffs_Leßte',
	'sacl-property-visibility' => 'Seeschbaa för',
	'sacl-property-visibility-wl-group' => 'Seeschbaa för de Jropp',
	'sacl-property-visibility-wl-user' => 'Seeschbaa för Metmaacher',
	'sacl-property-editable' => 'Änderbaa vun',
	'sacl-property-editable-wl-group' => 'Änderbaa vun ene Jropp', # Fuzzy
	'sacl-property-editable-wl-user' => 'Änderbaa vun enem Metmaacher', # Fuzzy
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'sacl-denied' => 'Dir sidd net op der Lëscht vun deenen déi dës Säit benotzen däerfen',
	'sacl-property-visibility' => 'Visibel fir',
	'sacl-property-visibility-wl-group' => 'Visibel fir de Grupp',
	'sacl-property-visibility-wl-user' => 'Visibel fir de Benotzer',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'sacl-desc' => 'Овозможува поставање на ограничувања на пристапот со својства на Семантичко МедијаВики',
	'sacl-denied' => 'Не сте на списокот за пристап до оваа страница.',
	'right-sacl-exempt' => 'Изземен од семантичкиот список за конрола на пристап',
	'sacl-property-visibility' => 'Видливо за',
	'sacl-property-visibility-wl-group' => 'Видливо за групата',
	'sacl-property-visibility-wl-user' => 'Видливо за корисникот',
	'sacl-property-editable' => 'Уредливо за',
	'sacl-property-editable-wl-group' => 'Уредливо за групата',
	'sacl-property-editable-wl-user' => 'Уредливо за корисникот',
);

/** Norwegian Bokmål (norsk bokmål)
 * @author Event
 */
$messages['nb'] = array(
	'sacl-desc' => 'Tillater å sette adgangsbegrensninger med egenskaper fra Semantic MediaWiki',
	'sacl-denied' => 'Du er ikke på adgangslisten for denne siden.',
	'right-sacl-exempt' => 'Fritatt fra Semantic ACL-er',
	'sacl-property-visibility' => 'Synlig for',
	'sacl-property-visibility-wl-group' => 'Synlig for gruppe',
	'sacl-property-visibility-wl-user' => 'Synlig for bruker',
	'sacl-property-editable' => 'Redigerbar av',
	'sacl-property-editable-wl-group' => 'Redigerbar av gruppe',
	'sacl-property-editable-wl-user' => 'Redigerbar av bruker',
);

/** Dutch (Nederlands)
 * @author McDutchie
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'sacl-desc' => 'Maakt het mogelijk toegangsbeperkingen in te stellen met eigenschappen van Semantic MediaWiki',
	'sacl-denied' => 'U staat niet op de toegangslijst voor deze pagina.',
	'right-sacl-exempt' => "Vrijgesteld van semantische ACL's (toegangscontrolelijsten)",
	'sacl-property-visibility' => 'Zichtbaar voor',
	'sacl-property-visibility-wl-group' => 'Zichtbaar voor de groep',
	'sacl-property-visibility-wl-user' => 'Zichtbaar voor gebruiker',
	'sacl-property-editable' => 'Te bewerken door',
	'sacl-property-editable-wl-group' => 'Te bewerken door groep',
	'sacl-property-editable-wl-user' => 'Te bewerken door gebruiker',
);

/** Polish (polski)
 * @author BeginaFelicysym
 * @author Woytecr
 */
$messages['pl'] = array(
	'sacl-desc' => 'Umożliwia ograniczenia dostępu przy użyciu właściwości Semantycznej MediaWiki',
	'sacl-denied' => 'Nie jesteś na liście dostępu do tej strony.',
	'right-sacl-exempt' => 'Zwolniony z listy ACL Semantycznej MediaWiki',
	'sacl-property-visibility' => 'Widoczne dla',
	'sacl-property-visibility-wl-group' => 'Widoczne dla grupy',
	'sacl-property-visibility-wl-user' => 'Widoczne dla użytkownika',
	'sacl-property-editable' => 'Możliwość edytowania przez',
	'sacl-property-editable-wl-group' => 'Edycja dla grup',
	'sacl-property-editable-wl-user' => 'Edycja przez użytkownika',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Dragonòt
 */
$messages['pms'] = array(
	'sacl-desc' => "A përmët che le restrission d'acess a sio ampostà con le propietà ëd Semantic MediaWiki",
	'sacl-denied' => "A l'é pa an sla lista d'acess për costa pàgina.",
	'right-sacl-exempt' => "Esentà da la lista d'intrada ëd Semàntich",
	'sacl-property-visibility' => 'Visìbil a',
	'sacl-property-visibility-wl-group' => 'Visìbil a la partìa',
	'sacl-property-visibility-wl-user' => "Visìbil a l'utent",
	'sacl-property-editable' => 'Modificàbil da',
	'sacl-property-editable-wl-group' => 'Modificàbi da la partìa',
	'sacl-property-editable-wl-user' => "Modificàbil da l'utent",
);

/** Portuguese (português)
 * @author Hamilton Abreu
 */
$messages['pt'] = array(
	'sacl-desc' => 'Permite definir restrições de acesso com as propriedades do MediaWiki Semântico',
	'sacl-denied' => 'Não está na lista de acesso desta página.',
	'right-sacl-exempt' => 'Isenta das Listas de Controle de Acesso Semânticas',
	'sacl-property-visibility' => 'Visível para',
	'sacl-property-visibility-wl-group' => 'Visível para o grupo',
	'sacl-property-visibility-wl-user' => 'Visível para o utilizador',
	'sacl-property-editable' => 'Editável por',
	'sacl-property-editable-wl-group' => 'Editável pelo grupo',
	'sacl-property-editable-wl-user' => 'Editável pelo utilizador',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Jaideraf
 */
$messages['pt-br'] = array(
	'sacl-desc' => 'Permite definir restrições de acesso com propriedades do Semantic Mediawiki',
	'sacl-denied' => 'Você não está na lista de acesso para esta página.',
	'right-sacl-exempt' => 'Isenta das Listas de Controle de Acesso Semânticas',
	'sacl-property-visibility' => 'Visível para',
	'sacl-property-visibility-wl-group' => 'Visível para o grupo',
	'sacl-property-visibility-wl-user' => 'Visível para o usuário',
	'sacl-property-editable' => 'Editável por',
	'sacl-property-editable-wl-group' => 'Editável pelo grupo',
	'sacl-property-editable-wl-user' => 'Editável pelo usuário',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'sacl-desc' => "Permette ca le restriziune d'accesse avènene 'mbostate cu le probbietà Semandeche de MediaUicchi",
	'sacl-denied' => "Non ge stè jndr'à l'elenghe d'accesse pe sta pàgene.",
	'right-sacl-exempt' => "Escluse da 'a Semandeche ACL",
	'sacl-property-visibility' => 'Vesibbile a',
	'sacl-property-visibility-wl-group' => "Visibbile a 'u gruppe",
	'sacl-property-visibility-wl-user' => "Visibbile a l'utende",
	'sacl-property-editable' => 'Cangiabbile da',
	'sacl-property-editable-wl-group' => "Cangiabbile da 'u gruppe",
	'sacl-property-editable-wl-user' => "Cangiabbile da l'utende",
);

/** Russian (русский)
 * @author Okras
 */
$messages['ru'] = array(
	'sacl-desc' => 'Позволяет установить ограничения доступа с помощью свойств Семантической Медиавики',
	'sacl-denied' => 'Вас нет в списке доступа для данной страницы.',
	'right-sacl-exempt' => 'исключение из семантических списков контроля доступа',
	'sacl-property-visibility' => 'Видна для',
	'sacl-property-visibility-wl-group' => 'Видна группе',
	'sacl-property-visibility-wl-user' => 'Видна участнику',
	'sacl-property-editable' => 'Доступна для редактирования',
	'sacl-property-editable-wl-group' => 'Доступна для редактирования группой',
	'sacl-property-editable-wl-user' => 'Доступна для редактирования участником',
);

/** Sinhala (සිංහල)
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'right-sacl-exempt' => 'අර්ථ විචාර ACLs වෙතින් නිදහස් වෙන්න',
	'sacl-property-visibility' => 'බැලිය හැක්කේ',
	'sacl-property-visibility-wl-group' => 'සමූහයට දෘශ්‍යමාන',
	'sacl-property-visibility-wl-user' => 'පරිශීලකට දෘශ්‍යමාන',
	'sacl-property-editable' => 'සංස්කරණය කල හැකි',
	'sacl-property-editable-wl-group' => 'සමූහය විසින් සංස්කරණය කල හැකි',
	'sacl-property-editable-wl-user' => 'පරිශීලක විසින් සංස්කරණය කල හැකි',
);

/** Swedish (svenska)
 * @author Martinwiss
 */
$messages['sv'] = array(
	'sacl-desc' => 'Möjliggör att rättigheter tilldelas med hjälp av egenskaper från Semantiska MediaWiki',
	'sacl-denied' => 'Du finns inte med på listan över de som har tillgång till den här sidan.',
	'right-sacl-exempt' => 'Utdrag från Semantic ACL:er',
	'sacl-property-visibility' => 'Synlig för',
	'sacl-property-visibility-wl-group' => 'Synlig för grupp',
	'sacl-property-visibility-wl-user' => 'Synlig för användare',
	'sacl-property-editable' => 'Redigerbar för',
	'sacl-property-editable-wl-group' => 'Redigerbar för grupp',
	'sacl-property-editable-wl-user' => 'Redigerbar för användare',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'sacl-desc' => 'Nagpapahintulot na maitakda ang mga kabawalan sa pagpunta na mayrooong mga katangiang-ari ng Semantikong MediaWiki',
	'sacl-denied' => 'Wala ka sa listahan ng mga makakapunta para sa pahinang ito.',
	'right-sacl-exempt' => 'Hindi kasali sa Semantikong Listahan ng Pagtaban sa Pagpunta',
	'sacl-property-visibility' => 'Makikita ng',
	'sacl-property-visibility-wl-group' => 'Makikita ng pangkat',
	'sacl-property-visibility-wl-user' => 'Makikita ng tagagamit',
	'sacl-property-editable' => 'Mapapatnugutan ng',
	'sacl-property-editable-wl-group' => 'Mapapatnugutan ng pangkat',
	'sacl-property-editable-wl-user' => 'Mapapatnugutan ng tagagamit',
);

/** Ukrainian (українська)
 * @author Base
 */
$messages['uk'] = array(
	'sacl-desc' => 'Дозволяє обмежувати доступ через властивості Semantic MediaWiki',
	'sacl-denied' => 'Ви не у списку доступу до цієї сторінки',
	'right-sacl-exempt' => 'Вийняток із семантичних списків котролю доступу (Semantic ACL)',
	'sacl-property-visibility' => 'Видима',
	'sacl-property-visibility-wl-group' => 'Видима групі',
	'sacl-property-visibility-wl-user' => 'Видима користувачу',
	'sacl-property-editable' => 'Може редагуватись',
	'sacl-property-editable-wl-group' => 'Може редагуватись групою',
	'sacl-property-editable-wl-user' => 'Може редагуватись користувачем',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Hzy980512
 * @author Linforest
 * @author Liuxinyu970226
 */
$messages['zh-hans'] = array(
	'sacl-desc' => '允许采用Semantic MediaWiki属性来设置访问限制',
	'sacl-denied' => '您不在该页面的可视列表中',
	'right-sacl-exempt' => 'Semantic ACL豁免权',
	'sacl-property-visibility' => '可见于',
	'sacl-property-visibility-wl-group' => '可见于组',
	'sacl-property-visibility-wl-user' => '可见于用户',
	'sacl-property-editable' => '可编辑于',
	'sacl-property-editable-wl-group' => '可编辑于组',
	'sacl-property-editable-wl-user' => '可编辑于用户',
);

/** Traditional Chinese (中文（繁體）‎)
 */
$messages['zh-hant'] = array(
	'sacl-desc' => '允許採用Semantic MediaWiki屬性來設置訪問限制',
	'sacl-denied' => '您不在該頁面的可視列表中',
	'right-sacl-exempt' => 'Semantic ACL豁免權',
	'sacl-property-visibility' => '可見於',
	'sacl-property-visibility-wl-group' => '可見於組',
	'sacl-property-visibility-wl-user' => '可見於用戶',
	'sacl-property-editable' => '可編輯於',
	'sacl-property-editable-wl-group' => '可編輯於組',
	'sacl-property-editable-wl-user' => '可編輯於用戶',
);
