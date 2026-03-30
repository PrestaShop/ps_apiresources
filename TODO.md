# TODO

## GET /carriers/{carrierId}/ranges — Core limitation

**Stato:** Endpoint implementato ma non funzionante (restituisce 422)

**Causa:** `CarrierRangeRepository::assertShopConstraint()` nel core PrestaShop accetta
solo `ShopConstraint::allShops()`, ma il framework API passa sempre un constraint
shop-specifico (es. `shopId=1`). La funzione `applyShopConstraint()` ha un TODO
irrisolto nel core.

**File coinvolti:**
- Modulo: `src/ApiPlatform/Resources/Carrier/CarrierRanges.php`
- Core PS: `src/Adapter/Carrier/Repository/CarrierRangeRepository.php` (righe 220-237)

**Fix richiesto:**
1. Aprire una issue/PR nel repo `PrestaShop/PrestaShop` per implementare
   `CarrierRangeRepository::applyShopConstraint()` con supporto ai constraint shop-specifici
2. Una volta fixato il core, verificare che l'endpoint funzioni correttamente
3. Valutare se rimuovere o mantenere il mapping `CarrierConstraintException → 422`

**Workaround attuale:** `CarrierConstraintException` mappata a HTTP 422 invece di 500.
