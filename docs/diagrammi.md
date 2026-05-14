# Diagrammi del progetto

## Diagramma dei casi d'uso

![TradeMarketAI - Architettura Casi d'Uso](./images/diagrammadefinitivo.png)

## Scenario dettagliato

### CU-Upgrade: passaggio da Free a Premium

**Attore principale:** Utente Free

**Precondizioni:**
- l'utente ha effettuato il login;
- il profilo è attivo;
- il parametro `transactions_enabled` nel database è abilitato.

**Flusso principale:**
1. L'utente apre la dashboard.
2. Il sistema mostra il pulsante di upgrade solo agli account Free.
3. L'utente conferma il cambio piano.
4. Il sistema esegue una transazione sul database.
5. Il sistema verifica che le transazioni siano abilitate.
6. Il sistema controlla che l'utente sia ancora Free.
7. Il sistema registra la transazione in `subscription_transactions`.
8. Il sistema aggiorna il ruolo dell'utente a Premium.
9. Il sistema conferma l'avvenuto upgrade.

**Postcondizioni:**
- il ruolo dell'utente diventa Premium;
- le funzionalità Premium diventano disponibili nella dashboard.

**Eccezioni:**
- se `transactions_enabled` è disattivato, il sistema interrompe l'operazione e mostra l'errore di transazioni disattivate;
- se l'utente non è più Free, l'operazione viene rifiutata;
- se manca la configurazione necessaria nel database, il sistema segnala un errore di configurazione.

## Diagramma delle classi

> Nota: il progetto è implementato in stile procedurale PHP, quindi questo diagramma rappresenta il modello logico del dominio e i moduli di servizio corrispondenti.

classDiagram
direction LR

%% =========================
%% AUTH DOMAIN
%% =========================

namespace Auth {

    class User {
        +int id
        +string username
        +string email
        +string password_hash
        +int role_id
        +bool is_active
    }

    class Role {
        +int id
        +string name
        +string description
    }

    class Permission {
        +int id
        +string name
        +string description
    }

    class RefreshToken {
        +int id
        +int user_id
        +string token_hash
        +datetime expires_at
        +bool revoked
    }

    class RolePermission {
        +int role_id
        +int permission_id
    }

    class UserPermission {
        +int user_id
        +int permission_id
    }
}

%% =========================
%% PORTFOLIO DOMAIN
%% =========================

namespace Portfolio {

    class Portfolio {
        +int id
        +int user_id
        +string name
    }

    class PortfolioItem {
        +int id
        +int portfolio_id
        +string symbol
        +int quantity
        +decimal purchase_price
    }
}

%% =========================
%% MONITORING DOMAIN
%% =========================

namespace Monitoring {

    class Alert {
        +int id
        +int user_id
        +string symbol
        +string condition_type
        +decimal threshold
        +bool is_active
    }
}

%% =========================
%% MARKET DOMAIN
%% =========================

namespace Market {

    class MarketData {
        +int id
        +string symbol
        +string name
        +decimal price
        +decimal change_pct
        +int volume
        +int market_cap
    }
}

%% =========================
%% TRANSACTIONS DOMAIN
%% =========================

namespace Transactions {

    class SubscriptionTransaction {
        +int id
        +int user_id
        +int from_role_id
        +int to_role_id
        +string status
        +datetime transaction_date
    }
}

%% =========================
%% CONFIG DOMAIN
%% =========================

namespace Config {

    class SystemSetting {
        +string setting_key
        +string setting_value
    }
}

%% =========================
%% SERVICES LAYER
%% =========================

namespace Services {

    class AuthService {
        +registerUser()
        +loginUser()
        +logoutUser()
        +getCurrentUser()
        +can()
    }

    class JwtService {
        +jwtCreate()
        +jwtVerify()
        +refreshTokenCreate()
        +refreshTokenRotate()
        +refreshTokenRevoke()
    }

    class PermissionService {
        +hasRolePermission()
        +hasUserPermission()
    }

    class PortfolioService {
        +getPortfolioValue()
        +addItem()
        +removeItem()
    }

    class AlertService {
        +createAlert()
        +disableAlert()
        +checkAlerts()
    }

    class MarketDataService {
        +fetchMarketData()
        +getMarketDataBySymbol()
    }

    class SubscriptionService {
        +upgradeToPremium()
        +validateTransaction()
    }

    class DashboardController <<boundary>> {
        +renderMarket()
        +renderPortfolio()
        +renderAlerts()
        +renderAdminPanel()
    }
}

%% =========================
%% RELAZIONI AUTH (CORRETTE UML)
%% =========================

User "*" --> "1" Role : belongs_to

User "1" --> "*" RefreshToken : owns

Role "1" --> "*" RolePermission
Permission "1" --> "*" RolePermission

User "1" --> "*" UserPermission
Permission "1" --> "*" UserPermission

%% =========================
%% PORTFOLIO RELATIONS
%% =========================

User "1" --> "*" Portfolio : owns
Portfolio "1" --> "*" PortfolioItem : contains

%% =========================
%% MONITORING RELATIONS
%% =========================

User "1" --> "*" Alert : creates

%% =========================
%% MARKET RELATIONS
%% =========================

PortfolioItem "*" ..> "1" MarketData : symbol
Alert "*" ..> "1" MarketData : symbol

%% =========================
%% TRANSACTIONS
%% =========================

User "1" --> "*" SubscriptionTransaction : performs

%% =========================
%% SERVICE DEPENDENCIES
%% =========================

AuthService ..> User
AuthService ..> Role
AuthService ..> Permission
AuthService ..> JwtService

JwtService ..> RefreshToken

PermissionService ..> RolePermission
PermissionService ..> UserPermission

PortfolioService ..> Portfolio
PortfolioService ..> PortfolioItem
PortfolioService ..> MarketData

AlertService ..> Alert
AlertService ..> MarketData

MarketDataService ..> MarketData

SubscriptionService ..> SubscriptionTransaction
SubscriptionService ..> SystemSetting

DashboardController ..> AuthService
DashboardController ..> PortfolioService
DashboardController ..> AlertService
DashboardController ..> MarketDataService
DashboardController ..> PermissionService
