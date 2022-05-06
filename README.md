# xtremedrupal

## Instalação
- Criar o arquivo settings local: ```cp web/sites/example.settings.local.php web/sites/default/settings.local.php``
- Criar o arquivo settings padrão: ```cp web/sites/default/example.settings.php web/sites/default/settings.php``
- Iniciar ambiente: ```lando start``
- Instalar Drupal: ```lando composer install``
- Subir banco: ```lando db-import db/database.sql.gz``
- Importar configurações: ```lando drush cim -y``
- Limpar cache: ```lando drush cr``
- Acessar projeto: ```lando drush uli``
