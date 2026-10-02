# Note de conception - EventFlow

Ce document formalise les choix d'architecture et de refactoring réalisés sur le projet EventFlow, conformément aux principes enseignés dans le cours **Design Patterns & Clean Code** (Nicolas-Paul Albrecht).

---

## 1. Choix principaux

Notre démarche a suivi la règle d'or du cours : **"Comprendre plus vite, modifier plus sereinement"**, en évitant à tout prix l'overengineering.

1. **Décomposition modulaire de la God Method (`BookingService::confirm`) :**
   * Au départ, `BookingService` concentrait toutes les responsabilités (calcul, paiement, persistence SQL console, envoi d'email).
   * Nous avons extrait chaque responsabilité dans un composant dédié et autonome :
     * Calcul tarifaire -> `PricingService`
     * Passerelle de paiement -> `PaymentGatewayInterface`
     * Actions post-confirmation -> `BookingConfirmationListenerInterface`
     * Persistance des données -> `BookingRepositoryInterface`
   * Résultat : `BookingService` est désormais un orchestrateur de haut niveau, lisible en moins de 30 secondes.

2. **Préservation du code externe et contrat d'adaptation :**
   * `PayFastSdk.php` étant un SDK tiers immuable aux types différents (montant en centimes, tableau associatif), nous avons isolé ses spécificités dans un adaptateur dédié sans modifier une seule ligne du SDK d'origine.

3. **Supervision technique non intrusive :**
   * Les besoins transversaux de journalisation et de mesure du temps de paiement ont été encapsulés autour de la passerelle sans polluer les règles métier du domaine.

4. **Couverture de tests exhaustive (57 tests unitaires et d'intégration) :**
   * Chaque brique métier dispose de sa suite de tests dédiée, permettant de refactorer sans craindre d'effets de bord.

---

## 2. Principes SOLID mobilisés

### S — Single Responsibility Principle (SRP)
* **Problème initial :** `BookingService::confirm()` avait au moins 5 raisons différentes de changer (modification des règles VIP, changement de prestataire de paiement, ajout d'une notification, évolution du schéma de persistance).
* **Classes concernées :** `BookingService`, `PricingService`, `EmailConfirmationListener`, `LoyaltyPointsListener`, `AnalyticsTrackingListener`, `SmsNotificationListener`, `SqlSimulationBookingRepository`.
* **Bénéfice obtenu :** Chaque classe a désormais une responsabilité unique et explicite. Si les règles de fidélité changent, seul `LoyaltyPointsListener` est impacté.

### O — Open / Closed Principle (OCP)
* **Problème initial :** Ajouter PayFast nécessitait d'ajouter un `elseif` dans `BookingService`. Ajouter une notification imposait de modifier la fin de la méthode `confirm()`.
* **Classes concernées :** `BookingService`, `PaymentGatewayInterface`, `BookingConfirmationListenerInterface`.
* **Bénéfice obtenu :** Le système est ouvert à l'extension mais fermé à la modification. Pour ajouter un paiement `GiftCard` ou une notification `Slack`, il suffit d'écrire une nouvelle classe implémentant l'interface correspondante et de l'injecter au démarrage, sans toucher à une seule ligne de `BookingService`.

### L — Liskov Substitution Principle (LSP)
* **Problème initial :** Dans le code initial, demander le paiement PayFast déclenchait `throw new RuntimeException('PayFast not implemented')`, ce qui violait la promesse faite par la signature de la méthode.
* **Classes concernées :** `StripePaymentGateway`, `PayFastAdapter`, `SupervisedPaymentGateway` implémentant `PaymentGatewayInterface`.
* **Bénéfice obtenu :** N'importe quelle passerelle de paiement peut être substituée à une autre de manière transparente. Toutes respectent le contrat de retourner un identifiant de transaction sous forme de `string` ou de lever une exception explicite en cas d'échec.

### I — Interface Segregation Principle (ISP)
* **Problème initial :** Risque de créer une interface "fourre-tout" avec des méthodes inutiles (ex: `pay()`, `refund()`, `sendMail()`, `save()`).
* **Classes concernées :** `PaymentGatewayInterface` (1 seule méthode : `charge`), `BookingConfirmationListenerInterface` (1 seule méthode : `onBookingConfirmed`), `BookingRepositoryInterface` (1 seule méthode : `save`).
* **Bénéfice obtenu :** Des interfaces fines, hautement ciblées, faciles à implémenter et à mocker dans les tests unitaires.

### D — Dependency Inversion Principle (DIP)
* **Problème initial :** `BookingService` dépendait directement de concrétions techniques instanciées avec `new StripeClient()` et `new EmailService()`.
* **Classes concernées :** `BookingService`, `PaymentGatewayInterface`, `BookingRepositoryInterface`, `BookingConfirmationListenerInterface`.
* **Bénéfice obtenu :** Le module de haut niveau (`BookingService`) dépend désormais exclusivement d'abstractions (interfaces). Les détails techniques (Stripe, PayFast, SQL, SMS) sont injectés par constructeur.

---

## 3. Design Patterns utilisés (et justification Clean Code)

### 1. Adapter (Pattern Structurel)
* **Problème rencontré :** `PayFastSdk` attend un tableau avec `amount_cents` (centimes) et une `reference`, alors que notre domaine manipule des montants en euros (`float`). Il était strictement interdit de modifier `PayFastSdk.php`.
* **Solution retenue :** `PayFastAdapter` implémente `PaymentGatewayInterface` et assure la conversion de l'euro vers les centimes, prépare le payload et traduit le tableau de résultat en identifiant de transaction.
* **Pourquoi une solution plus simple ne suffisait pas :** Écrire la conversion directement dans `BookingService` aurait introduit du couplage fort et de la Primitive Obsession dans le métier.

### 2. Decorator (Pattern Structurel)
* **Problème rencontré :** Le ticket #105 demandait de chronométrer l'exécution et de journaliser le montant et le statut de chaque paiement, sans modifier ni les SDKs tiers, ni la logique métier.
* **Solution retenue :** `SupervisedPaymentGateway` enveloppe n'importe quel `PaymentGatewayInterface`. Il intercepte l'appel, mesure la durée en millisecondes avec `microtime(true)`, logue le début et la fin (succès ou échec), et propage l'exception si le paiement échoue.
* **Pourquoi une solution plus simple ne suffisait pas :** Mettre des logs dans chaque adaptateur aurait créé de la duplication inutile. Mettre les logs dans `BookingService` aurait pollué le métier avec une responsabilité technique transversale.

### 3. Observer / Event Dispatcher (Pattern Comportemental)
* **Problème rencontré :** Lors de la confirmation d'une réservation, plusieurs réactions indépendantes doivent se produire (Email, Points de fidélité, Analytics, SMS conditionnel), et le client indique que d'autres actions s'ajouteront plus tard.
* **Solution retenue :** `BookingConfirmationListenerInterface` agit comme contrat d'écouteur. `BookingService` itère sur ses écouteurs enregistrés et les notifie.
* **Pourquoi une solution plus simple ne suffisait pas :** Appeler chaque service les uns après les autres dans `BookingService` aurait recréé une méthode trop chargée et violé le principe Ouvert/Fermé (OCP).

### 4. Repository (Pattern d'Architecture)
* **Problème rencontré :** La persistance était matérialisée par un `echo "SQL INSERT ..."` au beau milieu du domaine.
* **Solution retenue :** `BookingRepositoryInterface` avec son implémentation par défaut `SqlSimulationBookingRepository`.
* **Bénéfice :** Permet d'injecter un `InMemoryBookingRepository` dans les tests unitaires pour tester `BookingService` sans aucun affichage polluant sur la sortie standard.

### 🛡️ Absence d'overengineering (Anti-Patterns évités)
Conformément aux mises en garde du cours (Page 50 : *"Un design pattern peut rendre un petit problème inutilement complexe"*), nous avons délibérément écarté :
* **Pas d'Abstract Factory :** Inutile car l'injection par tableau associatif dans le constructeur de `BookingService` est 10 fois plus simple et parfaitement lisible.
* **Pas de Command Bus / Médiateur complexe :** Pour 4 actions post-confirmation, une simple boucle sur une liste d'écouteurs d'interface `BookingConfirmationListenerInterface` suffit amplement sans empiler des couches d'indirection.

---

## 4. Solutions envisagées puis écartées

| Solution écartée | Pourquoi elle a été écartée |
|---|---|
| **Héritage pour PayFast (`class PayFastAdapter extends PayFastSdk`)** | Violait le principe de favoriser la composition plutôt que l'héritage (*Composition over Inheritance*). De plus, `PayFastSdk` est marquée `final`. |
| **Gérer la supervision directement dans `BookingService`** | Aurait réintroduit de la dette technique transversale au sein du flux métier. |
| **Créer une classe de stratégie par palier VIP** | Créer 4 classes différentes pour 5%, 10% et 15% aurait été un cas d'école d'overengineering. Centraliser la table de barème dans `PricingService` est plus clair et facile à faire évoluer. |

---

## 5. Ce que nous améliorerions avec plus de temps

Si nous disposions de davantage de temps, nous pousserions les refactorings suivants :
1. **Éradication de la Primitive Obsession (Page 23 du cours) :**
   * Remplacer les `float $total` par un Value Object `Money` encapsulant le montant en centimes et la devise (`EUR`), éliminant définitivement les risques d'erreurs d'arrondi à virgule flottante.
   * Encapsuler les adresses email dans un Value Object `Email` auto-validant son format dès la construction.
2. **Gestion fine des erreurs de paiement :**
   * Remplacer l'usage générique de `RuntimeException` par des exceptions de domaine typées (`PaymentFailedException`, `PaymentNetworkTimeoutException`).
3. **Bonus fonctionnel (Annulation et Remboursement) :**
   * Étendre `PaymentGatewayInterface` avec une méthode `refund(string $transactionId, float $amount): bool` pour orchestrer le remboursement automatique en cas d'annulation.
