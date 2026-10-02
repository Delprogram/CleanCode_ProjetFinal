# EventFlow

Projet final B2 - Design Patterns & Clean Code (Easy-Comp / Nicolas-Paul Albrecht).

Application de billetterie pour festival de 3 jours, refactorée selon les principes Clean Code et SOLID.

---

## 📋 Prérequis

* PHP 8.1 ou supérieur.
* Aucun framework ni dépendance externe requise.

---

## 🚀 Lancer l'application

```bash
php index.php
```

Exécute un scénario complet :
1. Calcul tarifaire avec barème VIP et remises.
2. Débit sécurisé avec supervision (durée mesurée + montant logué).
3. Sauvegarde via `BookingRepository`.
4. Notification automatique des écouteurs (Email, Points fidélité, Analytics, SMS conditionnel).

---

## 🧪 Lancer la suite de tests

Le projet dispose d'une couverture complète de **57 tests automatisés (0 échec)** répartis en suites modulaires :

```bash
# 1. Tests de caractérisation initiaux (Ticket #101) - 11 tests
php tests/characterization.php

# 2. Tests de la politique tarifaire par paliers (Ticket #102) - 9 tests
php tests/pricing.php

# 3. Tests des passerelles Stripe et PayFast Adapter (Ticket #103) - 8 tests
php tests/payment.php

# 4. Tests de la supervision Decorator (Ticket #105) - 14 tests
php tests/supervision.php

# 5. Tests des écouteurs post-confirmation Observer (Ticket #104) - 12 tests
php tests/actions.php

# 6. Tests du Repository et découplage persistance (Ticket #106) - 3 tests
php tests/repository.php
```

### Exécuter tous les tests d'un coup :

Sous PowerShell (Windows) :
```powershell
php tests/characterization.php; php tests/pricing.php; php tests/payment.php; php tests/supervision.php; php tests/actions.php; php tests/repository.php
```

---

## 📚 Documentation d'architecture

* **[AUDIT.md](AUDIT.md)** : Diagnostic initial, les 6 signaux d'alerte classés par catégorie, nos 3 priorités d'action justifiées et l'analyse des risques.
* **[CONCEPTION.md](CONCEPTION.md)** : Note de conception détaillée, principes SOLID (SRP, OCP, LSP, ISP, DIP), Design Patterns retenus (Adapter, Decorator, Observer, Repository), solutions écartées et absence d'overengineering.
