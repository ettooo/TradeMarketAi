# Diagrammi del progetto

## Diagramma dei casi d'uso

```mermaid
graph LR
    subgraph Actors["👥 ATTORI"]
        UR["Utente Registrato<br/>(astratto)"]
        Free["Utente Free"]
        Premium["Utente Premium"]
        Admin["Amministratore"]
        AI["🤖 Sistema AI"]
        DataSources["📊 Fonti dati"]
    end
    
    subgraph Platform["🏢 TradeMarketAI Platform"]
        UC1["UC1<br/>Login/Registrazione"]
        UC2["UC2<br/>Dashboard"]
        UC3["UC3<br/>Mercati"]
        UC4["UC4<br/>Portafoglio"]
        UC5["UC5<br/>Alert Prezzo"]
        UC6["UC6<br/>Profilo"]
        UC7["UC7<br/>Analisi AI"]
        UC8["UC8<br/>Simulazioni"]
        UC9A["UC9<br/>Gestione Utenti"]
        UC10A["UC10<br/>Impostazioni"]
        UC11["UC11<br/>Upgrade Premium"]
        CU9["CU9<br/>Forum"]
        CU10["CU10<br/>Broker"]
        UC12["UC12<br/>Dati"]
    end
    
    Free -->|eredita| UR
    Premium -->|eredita| UR
    
    UR --> UC1
    UR --> UC2
    UR --> UC3
    UR --> UC4
    UR --> UC5
    UR --> UC6
    UR --> CU9
    
    Free --> UC11
    
    Premium --> UC7
    Premium --> UC8
    Premium --> CU10
    
    Admin --> UC9A
    Admin --> UC10A
    
    AI --> UC7
    AI --> UC8
    
    DataSources --> UC12
    
    UC2 -->|include| UC12
    UC3 -->|include| UC12
    UC4 -->|include| UC12
    UC5 -->|include| UC12
    
    UC11 -->|extend| UC6
    UC11 -->|extend| UC2
```

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

```mermaid
classDiagram
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

    class Alert {
        +int id
        +int user_id
        +string symbol
        +string condition_type
        +decimal threshold
        +bool is_active
    }

    class MarketData {
        +int id
        +string symbol
        +string name
        +decimal price
        +decimal change_pct
        +int volume
        +int market_cap
    }

    class RefreshToken {
        +int id
        +int user_id
        +string token_hash
        +datetime expires_at
        +bool revoked
    }

    class SystemSetting {
        +string setting_key
        +string setting_value
    }

    class SubscriptionTransaction {
        +int id
        +int user_id
        +int from_role_id
        +int to_role_id
        +string status
        +string notes
    }

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

    class DatabaseConnection {
        +getDB()
    }

    class Dashboard {
        +renderMarket()
        +renderPortfolio()
        +renderAlerts()
        +renderAdminPanel()
    }

    Role "1" --> "many" User
    User "1" --> "many" Portfolio
    Portfolio "1" --> "many" PortfolioItem
    User "1" --> "many" Alert
    User "1" --> "many" RefreshToken
    Role "many" --> "many" Permission
    User "1" --> "many" SubscriptionTransaction
    DatabaseConnection ..> AuthService
    DatabaseConnection ..> JwtService
    AuthService ..> JwtService
    AuthService ..> User
    JwtService ..> RefreshToken
    MarketData ..> Dashboard
```