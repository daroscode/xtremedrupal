# xtremedrupal

## Instalação / Estrutura:
- Criar o arquivo settings local: ```cp web/sites/xtreme.settings.local.php web/sites/default/settings.local.php```
- Criar o arquivo settings padrão: ```cp web/sites/default/example.settings.php web/sites/default/settings.php```
- Iniciar ambiente: ```lando start```
- Instalar Drupal: ```lando composer install```
- Subir banco: ```lando db-import db/database.sql.gz```
- Importar configurações: ```lando drush cim -y```
- Limpar cache: ```lando drush cr```
- Acessar projeto: ```lando drush uli```

## Instalação / Banco de dados:
Quando for criado algo que seja gravado no banco de dados (ao invés de configurações)
- Criar dump do banco: ```lando drush sql-dump > database.sql```
- Atualizar no repo: ```rm db/database.sql.gz && gzip -c database.sql > db/database.sql.gz```

## Instalação / Tema:

- Entre na pasta do tema: ```cd web/themes/custom/xtreme_bootstrap_sass/```
- Instale o Gulp: ```npm install --global gulp-cli```
- Instale as dependências: ```npm install```
- Para atualizar alterações feitas rode: ```gulp```

* Observação: Estas instruções são baseadas na versão 10.5.1 do Node
