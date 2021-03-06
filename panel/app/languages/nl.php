<?php

return array(
  'title' => 'Nederlands',
  'direction' => 'ltr',
  'author' => 'Donan Gallagher <donangallagher@gmail.com> and Rutger Laurman <info@lekkerduidelijk.nl>',
  'version' => '1.0.1',
  'data' => array(

    // global
    'cancel' => 'Annuleren',
    'add' => 'Toevoegen',
    'save' => 'Bewaar',
    'saved' => 'Bewaard!',
    'delete' => 'Verwijderen',
    'insert' => 'Invoegen',
    'ok' => 'Ok',

    // options (sidebar)
    'options.show' => 'Toon opties',
    'options.hide' => 'Verberg opties',

    // installation
    'installation' => 'Installatie',
    'installation.check.headline' => 'Kirby Panel Installatie',
    'installation.check.text' => 'Kirby heeft de volgende problemen ondervonden tijdens de installatie…',
    'installation.check.retry' => 'Opnieuw proberen',
    'installation.check.error' => 'Er zijn wat problemen!',
    'installation.check.error.accounts' => '/site/accounts is niet bewerkbaar',
    'installation.check.error.avatars' => '/assets/avatars is niet bewerkbaar',
    'installation.check.error.blueprints' => 'Voeg een /site/blueprints map toe',
    'installation.check.error.content' => 'De inhoud van de map en alle opgenomen bestanden en mappen moeten bewerkbaar zijn.',
    'installation.check.error.thumbs' => 'De thumbs map moet bewerkbaar zijn.',
    'installation.signup.username.label' => 'Maak uw eerste account',
    'installation.signup.username.placeholder' => 'Gebruikersnaam',
    'installation.signup.email.label' => 'Email',
    'installation.signup.email.placeholder' => 'mail@voorbeeld.com',
    'installation.signup.password.label' => 'Wachtwoord',
    'installation.signup.language.label' => 'Taal',
    'installation.signup.button' => 'Maak uw account',

    // login
    'login' => 'Login',
    'login.welcome' => 'Meld u aan met uw nieuwe account',
    'login.username.label' => 'Gebruikersnaam',
    'login.password.label' => 'Wachtwoord',
    'login.error' => 'Ongeldige gebruikersnaam of wachtwoord',
    'login.button' => 'Login',

    // logout
    'logout' => 'Uitloggen',

    // dashboard
    'dashboard' => 'Controlepaneel',
    'dashboard.index.pages.title' => 'Pagina\'s',
    'dashboard.index.pages.edit' => 'Edit',
    'dashboard.index.pages.add' => 'Toevoegen',
    'dashboard.index.site.title' => 'Uw site\'s URL',
    'dashboard.index.account.title' => 'Uw account',
    'dashboard.index.account.edit' => 'Bewerk',
    'dashboard.index.metatags.title' => 'Site variabelen',
    'dashboard.index.metatags.edit' => 'Bewerk',
    'dashboard.index.history.title' => 'Uw laatste updates',
    'dashboard.index.history.text' => 'De laatst gewijzigde pagina zal hier worden weergegeven om het gemakkelijk te maken ze later terug te vinden.',

    // metatags
    'metatags' => 'Site variabelen',
    'metatags.back' => 'Terug naar het controlpanel',

    // pages
    'pages.show.settings' => 'Pagina instellingen',
    'pages.show.preview' => 'Open voorbeeld',
    'pages.show.changeurl' => 'Verander URL',
    'pages.show.delete' => 'Verwijder deze pagina',
    'pages.show.subpages.title' => 'Paginas',
    'pages.show.subpages.edit' => 'Edit',
    'pages.show.subpages.add' => 'Toevoegen',
    'pages.show.subpages.empty' => 'Deze pagina heeft geen subpagina\'s',
    'pages.show.files.title' => 'Bestanden',
    'pages.show.files.edit' => 'Edit',
    'pages.show.files.add' => 'Toevoegen',
    'pages.show.files.empty' => 'Deze pagina heeft geen bestanden',
    'pages.show.error.permissions.title' => 'Deze pagina is niet bewerkbaar',
    'pages.show.error.permissions.text'  => 'Controleer de permissies voor de inhoud van de map en alle bestanden.',
    'pages.show.error.permissions.retry'  => 'Opnieuw proberen',
    'pages.show.error.notitle.title' => 'De blueprint heeft geen veld voor titel',
    'pages.show.error.notitle.text' => 'Voeg een veld voor titel toe en probeer opnieuw.',
    'pages.show.error.notitle.retry' => 'Opnieuw proberen',
    'pages.show.error.form'  => 'Vul alle velden correct in.',

    'pages.add.title.label' => 'Voeg een nieuwe pagina toe.',
    'pages.add.title.placeholder' => 'Titel',
    'pages.add.url.label' => 'URL-toevoeging',
    'pages.add.url.enter' => '(voer uw titel in)',
    'pages.add.url.close' => 'Sluit',
    'pages.add.url.help' => 'Format: onderkast a-z, 0-9 en reguliere streepjes',
    'pages.add.template.label' => 'Sjabloon',
    'pages.add.error.title' => 'De titel ontbreekt',
    'pages.add.error.template' => 'De template ontbreekt',
    'pages.add.error.max.headline' => 'Geen nieuwe paginas toegestaan',
    'pages.add.error.max.text' => 'Het maximum aantal subpaginas voor de huidige pagina is bereikt.',
    'pages.url.uid.label' => 'URL-toevoeging',
    'pages.url.uid.label.option' => 'Maak op basis van titel',
    'pages.url.error.exists' => 'Een pagina met de dezelfde toevoeging bestaat al.',
    'pages.url.error.move' => 'De toevoeging kon niet worden veranderd',
    'pages.delete.headline' => 'Wilt u deze pagina echt verwijderen?',
    'pages.delete.error.home.headline' => 'De home pagina kan niet worden verwijderd',
    'pages.delete.error.home.text' => 'U probeert de home pagina te verwijderen. Dit is niet mogelijk en zou kunnen leiden tot ongewenste resultaten.',
    'pages.delete.error.error.headline' => 'De error pagina kan niet worden verwijderd',
    'pages.delete.error.error.text' => 'U probeert de error pagina te verwijderen. Dit is niet mogelijk en zou kunnen leiden tot ongewenste resultaten.',
    'pages.delete.error.children.headline' => 'De pagina kan niet worden verwijderd',
    'pages.delete.error.children.text' => 'Deze pagina heeft subpaginas en kan niet worden verwijderd. Verwijder eerst alle subpaginas.',
    'pages.delete.error.blocked.headline' => 'De pagina kan niet worden verwijderd',
    'pages.delete.error.blocked.text' => 'Deze pagina is vergrendeld en kan niet worden verwijderd.',
    'pages.search.help' => 'Zoek paginas via URL. Navigeer door de zoekresultaten met de pijltoetsen en druk op enter om naar de gewenste pagina te gaan.',
    'pages.search.noresults' => 'Er zijn geen zoekresultaten voor uw zoekopdracht. Probeer het opnieuw met een andere URL.',
    'pages.error.missing' => 'De pagina kan niet worden gevonden',

    // subpages
    'subpages' => 'Paginas',
    'subpages.index.headline' => 'Pagina\'s in',
    'subpages.index.back' => 'Terug',
    'subpages.index.add' => 'Nieuwe pagina toevoegen',
    'subpages.index.add.first.text' => 'Deze pagina heeft nog geen subpagina\'s',
    'subpages.index.add.first.button' => 'Voeg de eerste pagina toe',
    'subpages.index.visible' => 'Zichtbare pagina\'s',
    'subpages.index.visible.help' => 'Sleep onzichtbare pagina\'s hier om ze zichtbaar te maken.',
    'subpages.index.invisible' => 'Onzichtbare pagina\'s',
    'subpages.index.invisible.help' => 'Sleep zichtbare pagina\'s hier om ze onzichtbaar te maken.',
    'subpages.error.missing' => 'De pagina kan niet worden gevonden',

    // files
    'files' => 'Bestanden',
    'files.index.headline' => 'Bestanden voor',
    'files.index.back' => 'Terug',
    'files.index.upload' => 'Upload een nieuw bestand',
    'files.index.upload.first.text' => 'Deze pagina heeft nog geen bestanden',
    'files.index.upload.first.button' => 'Upload het eerste bestand',
    'files.index.edit' => 'Bewerk',
    'files.index.delete' => 'Verwijder',
    'files.show.name.label' => 'Bestandsnaam',
    'files.show.info.label' => 'Type / Grootte / Afmetingen',
    'files.show.link.label' => 'Publieke link',
    'files.show.open' => 'Toon/download bestand',
    'files.show.back' => 'Terug',
    'files.show.replace' => 'Vervang',
    'files.show.delete' => 'Verwijder',
    'files.show.error.rename' => 'Het bestand kon niet worden hernoemd',
    'files.show.error.form' => 'Vul alle velden correct in',
    'files.upload.drop' => 'Bestanden hier neerzetten…',
    'files.upload.click' => '…of klik voor upload',
    'files.replace.drop' => 'Zet een bestand hier neer…',
    'files.replace.click' => '…of klik om te vervangen',
    'files.replace.error.type' => 'Het geüploade bestand moet hetzelfde bestandstype zijn',
    'files.delete.headline' => 'Weet u zeker dat u dit bestand wilt verwijderen?',
    'files.error.missing.page' => 'De pagina kan niet worden gevonden',
    'files.error.missing.file' => 'Het bestand kan niet worden gevonden',

    // users
    'users' => 'Gebruikers',
    'users.index.headline' => 'Alle gebruikers',
    'users.index.add' => 'Voeg een nieuwe gebruiker toe',
    'users.index.edit' => 'Bewerk',
    'users.index.delete' => 'Verwijder',
    'users.form.username.label' => 'Gebruikersnaam',
    'users.form.username.placeholder' => 'Uw gebruikersnaam',
    'users.form.username.help' => 'Toegestane tekens: onderkast a-z, 0-9 en streepjes',
    'users.form.username.readonly' => 'De gebruikersnaam kon niet worden gewijzigd',
    'users.form.firstname.label' => 'Voornaam ',
    'users.form.lastname.label' => 'Achternaam',
    'users.form.email.label' => 'E-mail',
    'users.form.email.placeholder' => 'mail@voorbeeld.com',
    'users.form.password.label' => 'Wachtwoord',
    'users.form.password.confirm.label' => 'Bevestig wachtwoord',
    'users.form.password.new.label' => 'Nieuw wachtwoord',
    'users.form.password.new.confirm.label' => 'Bevestig het nieuwe wachtwoord',
    'users.form.password.new.help' => 'Laat leeg om het huidige wachtwoord te behouden',
    'users.form.language.label' => 'Taal',
    'users.form.role.label' => 'Rol',
    'users.form.options.headline' => 'Account opties',
    'users.form.options.message' => 'Stuur email',
    'users.form.options.delete' => 'Verwijder account',
    'users.form.avatar.headline' => 'Profielfoto',
    'users.form.avatar.upload' => 'Upload profielfoto',
    'users.form.avatar.replace' => 'Vervang profielfoto',
    'users.form.avatar.delete' => 'Verwijder profielfoto',
    'users.form.back' => 'Terug naar gebruikers',
    'users.form.error.password.confirm' => 'Bevestig het wachtwoord',
    'users.form.error.update' => 'De gebruiker kon niet worden geüpdated',
    'users.form.error.create' => 'De gebruiker kon niet worden aangemaakt',
    'users.form.error.permissions.title' => 'De account map is niet bewerkbaar',
    'users.form.error.permissions.text' => 'Zorg ervoor dat /site/accounts bestaat en bewerkbaar is.',
    'users.delete.headline' => 'Weet u zeker dat u deze gebruiker wilt verwijderen?',
    'users.delete.error' => 'De gebruiker kon niet worden verwijderd',
    'users.avatar.drop' => 'Plaats hier een profielfoto…',
    'users.avatar.click' => '…of klik voor upload',
    'users.avatar.error.type' => 'Je kan alleen JPG, PNG en GIF bestanden uploaden',
    'users.avatar.error.folder.headline' => 'De avatar map is niet bewerkbaar',
    'users.avatar.error.folder.text' => 'Maak een map <strong>/assets/avatars</strong> aan en maak het bewerkbaar om profielfotos toe te voegen.',
    'users.avatar.delete.error' => 'De profielfoto kon niet worden verwijderd',
    'users.avatar.delete.success' => 'De profielfoto is verwijderd',
    'users.error.missing' => 'De gebruiker kon niet worden gevonden',

    // form fields
    'fields.required' => 'Verplicht',
    'fields.date.label' => 'Datum',
    'fields.date.months' => array(
      'Januari',
      'Februari',
      'Maart',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Augustus',
      'September',
      'Oktober',
      'November',
      'December'
    ),
    'fields.date.weekdays' => array(
      'Zondag',
      'Maandag',
      'Dinsdag',
      'Woensdag',
      'Donderdag',
      'Vrijdag',
      'Zaterdag'
    ),
    'fields.date.weekdays.short' => array(
      'Zon',
      'Maan',
      'Din',
      'Woe',
      'Don',
      'Vrij',
      'Zat'
    ),
    'fields.email.label' => 'E-mail',
    'fields.email.placeholder' => 'mail@voorbeeld.com',
    'fields.number.label' => 'Nummer',
    'fields.number.placeholder' => '#',
    'fields.page.label' => 'Pagina',
    'fields.page.placeholder' => 'pad/naar/pagina',
    'fields.password.label' => 'Wachtwoord',
    'fields.structure.add' => 'Toevoegen',
    'fields.structure.add.first' => 'Voeg de eerste melding toe',
    'fields.structure.empty' => 'Nog geen meldingen.',
    'fields.structure.cancel' => 'Annuleren',
    'fields.structure.save' => 'Bewaar',
    'fields.structure.edit' => 'Bewerk',
    'fields.structure.delete' => 'Verwijder',
    'fields.tags.label' => 'Tags',
    'fields.tel.label' => 'Telefoon',
    'fields.textarea.buttons.bold.label' => 'Tekst vet',
    'fields.textarea.buttons.bold.text' => 'Tekst vet',
    'fields.textarea.buttons.italic.label' => 'Tekst cursief',
    'fields.textarea.buttons.italic.text' => 'Tekst cursief',
    'fields.textarea.buttons.link.label' => 'Link',
    'fields.textarea.buttons.email.label' => 'E-mail',
    'fields.textarea.buttons.image.label' => 'Afbeelding',
    'fields.textarea.buttons.file.label' => 'Bestand',
    'fields.toggle.yes' => 'Ja',
    'fields.toggle.no' => 'Nee',
    'fields.toggle.on' => 'Aan',
    'fields.toggle.off' => 'Uit',

    // textarea overlays
    'editor.link.url.label' => 'URL invoegen',
    'editor.link.text.label' => 'Link text',
    'editor.link.text.help' => 'De link text is optioneel',
    'editor.email.address.label' => 'Voeg emailadres toe',
    'editor.email.address.placeholder' => 'mail@voorbeeld.com',
    'editor.email.text.label' => 'Link text',
    'editor.email.text.help' => 'De link text is optioneel',
    'editor.file.empty' => 'Deze pagina heeft geen bestanden',
    'editor.image.empty' => 'Deze pagina heeft geen afbeeldingen',

    // error page
    'error' => 'Foutmelding',
    'error.headline' => 'Foutmelding',

  )
);
