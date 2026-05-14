# TradeMarketAi - Diagramma delle Classi

Di seguito è riportato il diagramma delle classi (modello logico e strutturale) del progetto, che include esclusivamente le entità di dominio (tabelle del database) e i moduli applicativi principali (script e librerie procedurali del progetto), omettendo classi esterne e dipendenze.

```mermaid
classDiagram
direction TB

%% =========================
%% DOMINIO / ENTITÀ (Database)
%% =========================
namespace DatabaseEntities {
    class User {
        +int id
        +string username
        +string email
        +string password_hash
        +int role_id
        +bool is_active
        +datetime created_at
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
        +datetime created_at
        +datetime expires_at
        +bool revoked
    }

    class Portfolio {
        +int id
        +int user_id
        +string name
        +datetime created_at
    }

    class PortfolioItem {
        +int id
        +int portfolio_id
        +string symbol
        +int quantity
        +decimal purchase_price
        +datetime purchased_at
    }

    class Alert {
        +int id
        +int user_id
        +string symbol
        +string condition_type
        +decimal threshold
        +bool is_active
        +datetime created_at
    }

    class MarketData {
        +int id
        +string symbol
        +string name
        +decimal price
        +decimal change_pct
        +int volume
        +int market_cap
        +datetime fetched_at
    }
}

%% =========================
%% CORE / CONFIG
%% =========================
namespace ConfigCore {
    class DBConfig {
        <<module>>
        +getDB() PDO
    }

    class JWTConfig {
        <<module>>
        +jwtCreate(payload: array) string
        +jwtVerify(token: string) array
        +jwtFromRequest() array
        +refreshTokenCreate(user_id: int) string
        +refreshTokenRotate(token: string) array
        +refreshTokenRevoke(token: string) void
    }
}

%% =========================
%% API E ROUTER
%% =========================
namespace APILayer {
    class ApiRouter {
        <<controller>>
        +respond(code: int, data: array)
        +getBody() array
        +normalizeApiPath(uri: string) string
        +getEffectivePermissionNames(user_id: int) array
        +requireJwt() array
        +requireApiPermission(user_id: int, permission: string)
    }
}

%% =========================
%% VIEW / FRONT CONTROLLERS
%% =========================
namespace Views {
    class LoginPHP {
        <<page>>
        +renderLoginForm()
    }

    class DashboardPHP {
        <<page>>
        +renderDashboard()
        +displayMarket()
        +displayPortfolio()
    }

    class ProfilePHP {
        <<page>>
        +renderProfile()
    }
    
    class LogoutPHP {
        <<page>>
        +executeLogout()
    }
}

%% =========================
%% RELAZIONI
%% =========================

User "1" -- "*" RefreshToken : ha
User "*" -- "1" Role : appartiene_a
User "1" -- "*" Portfolio : possiede
User "1" -- "*" Alert : imposta

Role "*" -- "*" Permission : possiede (role_permissions)
User "*" -- "*" Permission : possiede (user_permissions)

Portfolio "1" -- "*" PortfolioItem : contiene

PortfolioItem "*" ..> "1" MarketData : fa_riferimento (symbol)
Alert "*" ..> "1" MarketData : fa_riferimento (symbol)

ApiRouter ..> DBConfig : usa
ApiRouter ..> JWTConfig : usa
ApiRouter ..> User : gestisce
ApiRouter ..> Permission : verifica
ApiRouter ..> Portfolio : gestisce
ApiRouter ..> Alert : gestisce

LoginPHP ..> ApiRouter : chiama
DashboardPHP ..> ApiRouter : chiama
ProfilePHP ..> ApiRouter : chiama
LogoutPHP ..> ApiRouter : chiama

```
