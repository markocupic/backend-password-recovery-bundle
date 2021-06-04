<img src="./src/Resources/public/logo.png" width="300">


# Contao Backend Password Recovery Bundle
Senden Sie Benutzern niemals Passwörter über E-Mail. 

Dieses Plugin blendet **nach** falscher Eingabe des **Backend User Passwortes** einen "Passwort-Wiederherstellen-Button" ein. Durch Eingabe des Benutzernamens oder der E-Mail-Adresse wird dem User **eine E-Mail mit einem Link** zugesandt. Damit kann der Backend User sein Passwort neu erstellen.

## Installation
Via composer mit `composer require markocupic/backend-password-recovery-bundle`
oder Contao Manager. Nach der Installation das Install-Tool für das Datenbank Update laufen lassen.

## Konfiguration
Nach der Installation ist keine weitere Konfiguration nötig. 
Der **E-Mail-Betreff** und **E-Mail-Text** können über die **Sprachdatei** angepasst werden.
```
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailSubject']
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailText'] 
```

## Bedienung
| Ungültige Passworteingabe | Benutzernamen oder E-Mail-Adresse eingeben | Benutzer erhält eine E-Mail mit Link zugesandt und richtet ein neues Passwort ein. |
|-|-|-|
| <img src="./src/Resources/public/print_screen_1.png" width="300"> | <img src="./src/Resources/public/print_screen_2.png" width="300"> | <img src="./src/Resources/public/print_screen_3.png" width="300"> |



## Wie bette ich den "Passwort vergessen" Link von Anfang an im Backend Login Template ein?
Mit  `$this->recoverPasswordLink` bekommst du im Login Template "be_login.html5" die url und mit `$this->forgotPassword` die Übersetzung.

<img src="./src/Resources/public/print_screen_4.png" width="300">

 
```
<!-- be_login.html5 -->          
<div class="submit_container cf">
  <button type="submit" name="login" id="login" class="tl_submit"><?= $this->loginButton ?></button>
  <a href="/" class="footer_preview"><?= $this->feLink ?> ›</a>
  <br>
  <!-- Show password forgot link -->
  <a href="<?= $this->recoverPasswordLink ?>" class="footer_preview"><?= $this->forgotPassword ?> ›</a>
</div>

 
```