:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
php vendor\bin\ecs check vendor/markocupic/backend-password-recovery-bundle/src --fix --config vendor/markocupic/backend-password-recovery-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/backend-password-recovery-bundle/contao --fix --config vendor/markocupic/backend-password-recovery-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/backend-password-recovery-bundle/config --fix --config vendor/markocupic/backend-password-recovery-bundle/tools/ecs/config.php
::php vendor\bin\ecs check vendor/markocupic/backend-password-recovery-bundle/tests --fix --config vendor/markocupic/backend-password-recovery-bundle/tools/ecs/config.php


