# Audit initial - EventFlow

## 1. Comportement observable

Lors de l'exécution du point d'entrée (`php index.php`) :
1. Une réservation (`Booking #1001`) est instanciée pour une cliente VIP (`lea@example.com`) avec 2 billets d'une journée à 79.90 € (montant brut : 159.80 €).
2. La méthode `BookingService::confirm()` applique la remise VIP initiale de 10 %, ce qui donne un total de **143.82 €**.
3. Un appel simulé à Stripe est effectué et affiche `PAYMENT stripe_143.82`.
4. Le statut de la réservation passe à `confirmed`.
5. Une fausse insertion SQL est affichée directement via `echo` dans la console : `SQL INSERT booking=1001 total=143.82 status=confirmed`.
6. Un email de confirmation est envoyé via `EmailService`, affichant `EMAIL lea@example.com: booking 1001 confirmed`.
7. Le total final calculé (143.82 €) est retourné et affiché.

---

## 2. Problèmes identifiés

| # | Problème | Catégorie | Impact |
|---|---|---|---|
| **1** | **Violation de la responsabilité unique (SRP)** : `BookingService::confirm()` orchestre et réalise directement la validation, le calcul du panier, l'application des remises, le paiement, la persistance simulée et l'envoi d'email. | *Responsabilité* | Classe "Dieu" difficile à maintenir. Toute modification d'une règle impose de retoucher ce service central. |
| **2** | **Couplage fort à l'infrastructure & violation de l'inversion des dépendances (DIP)** : `new StripeClient()` et `new EmailService()` sont instanciés directement dans la méthode `confirm()`. | *Couplage / Testabilité* | Impossible d'injecter des doublons de test (mocks/stubs) ou d'interchanger les prestataires sans modifier le code source métier. |
| **3** | **Gestion des moyens de paiement non extensible (violation OCP)** : Présence d'un `if ($paymentMethod === 'stripe') ... elseif ($paymentMethod === 'payfast')`. PayFast lance une `RuntimeException('PayFast not implemented')`. | *Conception / Extensibilité* | L'ajout de PayFast ou de tout futur moyen de paiement nécessite d'éditer la méthode métier au lieu de simplement injecter une implémentation. |
| **4** | **Règles tarifaires rigides et valeurs magiques** : Les pourcentages de remise (`0.90`) et déductions fixes (`10.0`) sont codés en dur dans la méthode. | *Règles métier / Lisibilité* | Impossible de faire évoluer le barème commercial (Ticket #102) sans risquer de casser le flux de confirmation. Pas de gestion des arrondis ni de contrôle de total négatif. |
| **5** | **Pollution du code métier par des effets de bord directs** : Appels directs à `echo` pour les logs de paiement et la requête SQL (`echo "SQL INSERT ..."`). | *Architecture / Lisibilité* | Aucune séparation entre le domaine métier et la supervision/persistance. La sortie console pollue les tests et empêche un monitoring propre. |
| **6** | **Couverture de tests initiale lacunaire** : Le fichier `tests/characterization.php` d'origine ne couvrait que 3 cas nominaux de calcul sans tester les erreurs (réservation vide, email invalide, quantité négative, méthode inconnue). | *Testabilité / Sécurité* | Risque élevé de régressions silencieuses lors des refactorings des tickets suivants. |

---

## 3. Nos trois priorités

1. **Priorité 1 — Sécurisation par tests de caractérisation complets :**
   * *Justification :* Avant toute modification du code existant, nous devons impérativement figer l'ensemble des comportements attendus (succès comme échecs) afin de pouvoir refactorer sereinement avec un filet de sécurité immédiat.
2. **Priorité 2 — Découplage de la passerelle de paiement (Interface + Adapter) :**
   * *Justification :* Le ticket #103 exige d'intégrer PayFast sans modifier son SDK, et le ticket #105 demande de superviser le paiement. Poser une abstraction `PaymentGatewayInterface` et un `PayFastAdapter` résout immédiatement le couplage dur et prépare le terrain pour le Decorator de supervision.
3. **Priorité 3 — Isolation du calcul tarifaire (Pricing / Strategy) :**
   * *Justification :* Les règles commerciales du festival changent immédiatement (Ticket #102 avec tranches VIP et réduction Pass 3 jours) et le métier annonce de futures évolutions. Extraire le calcul de prix hors de `BookingService` permet de le tester unitairement et de le rendre extensible.

---

## 4. Risques avant refactoring

1. **Régression sur les montants facturés :** Un changement mal isolé des formules de calcul de remise (VIP et Pass 3 jours) peut fausser les montants réclamés aux clients.
2. **Effets de bord lors de la séparation des responsabilités :** En déplaçant l'envoi d'email, la fausse persistance ou le paiement hors de `BookingService`, risque d'oublier ou de modifier l'ordre d'exécution attendu par les consommateurs existants (`index.php`).
3. **Incompatibilité avec le SDK PayFast :** Le SDK tiers `PayFastSdk` impose un contrat rigide (montant en centimes `amount_cents`, référence textuelle, tableau de retour) qui ne doit pas fuiter dans le modèle métier.
