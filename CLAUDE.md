# CLAUDE.md - Guide ps_apiresources

## Apercu

Module PrestaShop qui ajoute des endpoints Admin API via API Platform (CQRS).

## Structure

- `src/ApiPlatform/Resources/` — Definitions des ressources API Platform (1 classe = 1 endpoint group)
- `tests/Integration/ApiPlatform/` — Tests d'integration PHPUnit
- `tests/Integration/ApiPlatform/ApiTestCase.php` — Classe de base avec helpers (`getItem`, `createItem`, `updateItem`, etc.)

## Lancer les tests

Les tests necessitent un PrestaShop complet avec base de donnees. Deux methodes :

### Via le repo PrestaShop (recommande)

Monter le module en volume Docker dans le `docker-compose.yml` de PrestaShop :

```yaml
volumes:
  - /chemin/vers/ps_apiresources:/var/www/html/modules/ps_apiresources
```

Creer la base de test si pas fait :

```bash
docker compose exec -e _PS_ROOT_DIR_=/var/www/html prestashop-git composer create-test-db
```

Lancer les tests avec le PHPUnit du **core** (pas celui du module) :

```bash
docker compose exec -e _PS_ROOT_DIR_=/var/www/html prestashop-git bash -c \
  "cd modules/ps_apiresources && php /var/www/html/vendor/bin/phpunit \
  -c tests/Integration/phpunit-local.xml \
  tests/Integration/ApiPlatform/CartEndpointTest.php"
```

### Un seul test

```bash
docker compose exec -e _PS_ROOT_DIR_=/var/www/html prestashop-git bash -c \
  "cd modules/ps_apiresources && php /var/www/html/vendor/bin/phpunit \
  -c tests/Integration/phpunit-local.xml \
  tests/Integration/ApiPlatform/CartEndpointTest.php \
  --filter testCreateCart"
```

## Conventions de test

Ref : https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/#-phpunit-testing-strategy

- **Full Data Assertions** : assertEquals sur la reponse complete, pas assertArrayHasKey sur des champs individuels
- **Verification post-update** : apres chaque PUT, faire un GET et verifier le changement
- **Pas de commentaires inutiles** (separateurs, blocs decoratifs)

## Ressources API Platform

Chaque ressource definit :
- Les operations CQRS (CQRSGet, CQRSCreate, CQRSUpdate, CQRSDelete)
- Les scopes requis (cart_read, cart_write, etc.)
- Les proprietes publiques qui forment la structure de la reponse JSON

Exemple : `Cart.php` retourne `cartId`, `customerId`, `currencyId`, `customerInformation`, `orderInformation`, `cartSummary`.

## Points d'attention

- **Feature flag OBLIGATOIRE** : les scopes des nouvelles resources ne sont decouverts que si `admin_api_experimental_endpoints` est active en base :
  ```sql
  UPDATE ps_feature_flag SET state = 1 WHERE name = 'admin_api_experimental_endpoints';
  ```
  Sans cela, `createApiClient()` echoue avec "The scopes xxx are not associated to any installed API"
- Le framework de test utilise le Symfony Kernel test client (pas HTTP reel)
- Les scopes OAuth2 sont geres par le CommandBus interne
- Utiliser `DatabaseDump::restoreTables()` dans `setUpBeforeClass` pour isoler les tests
- Ne pas utiliser le PHPUnit du module (conflit de version avec le core)
