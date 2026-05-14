# Diagrammi del progetto

## Diagramma dei casi d'uso

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle
skinparam actorStyle awesome

title TradeMarketAI - Architettura Casi d'Uso Raffinata

' --- ATTORI ---
actor "Utente Registrato" as UR <<abstract>>
actor "Utente Free" as Free
actor "Utente Premium" as Premium
actor "Amministratore" as Admin
actor "Sistema AI" as AI <<system>>
actor "Fonti dati esterne" as DataSources <<service>>

' Ereditarietà per pulizia visiva
Free --|> UR
Premium --|> UR

rectangle "TradeMarketAI Platform" {

    ' Accesso e Base
    (UC1 - Login / Registrazione) as UC1
    (UC6 - Gestione Profilo) as UC6
    (UC11 - Upgrade a Premium) as UC11
    
    ' Operatività Standard
    (UC2 - Consultazione Dashboard) as UC2
    (UC3 - Visualizzazione Mercati) as UC3
    (UC4 - Portafoglio Virtuale) as UC4
    (UC5 - Gestione Alert Prezzo) as UC5
    (CU9 - Forum Community) as CU9
    
    ' Funzioni Avanzate
    (UC7 - Analisi Predittiva AI) as UC7
    (UC8 - Simulazioni Probabilistiche) as UC8
    (CU10 - Integrazione Broker) as CU10
    
    ' Backend e Admin
    (UC12 - Accesso e Normalizzazione Dati) as UC12
    (UC9_Admin - Gestione Utenti e Ruoli) as UC9A
    (UC10_Admin - Impostazioni Sistema) as UC10A
}

' --- RELAZIONI ---
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

' --- SISTEMI ESTERNI E LOGICA DATI ---
AI --> UC7
AI --> UC8

DataSources --> UC12
' I casi d'uso "Includono" la normalizzazione dati per funzionare
UC2 ..> UC12 : <<include>>
UC3 ..> UC12 : <<include>>
UC4 ..> UC12 : <<include>>
UC5 ..> UC12 : <<include>>

' L'upgrade estende le capacità della dashboard e del profilo
UC11 ..> UC6 : <<extend>>
UC11 ..> UC2 : <<extend>>

@enduml
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