<img src="./src/Resources/public/logo.png" width="300">


# Contao Backend Password Recovery Bundle
Dieses Plugin blendet bei falscher Eingabe des Backend Passwortes einen Link ein, womit das Passwort wiederhergestellt werden kann. 

Es ist keine weitere Konfiguration nötig.


## Wie bette ich den "Passwort vergessen" Link im Backend Login Template ein?
Mit  `$this->recoverPasswordLink` bekommst du im Login Template "be_login.html5" die url und mit `$this->forgotPassword` die Übersetzung.

 
```
           
            <div class="submit_container cf">
              <button type="submit" name="login" id="login" class="tl_submit"><?= $this->loginButton ?></button>
              <a href="/" class="footer_preview"><?= $this->feLink ?> ›</a>
              <br>
              /** Show password forgot link **/
              <a href="<?= $this->recoverPasswordLink ?>" class="footer_preview"><?= $this->forgotPassword ?> ›</a>
            </div>

 
```