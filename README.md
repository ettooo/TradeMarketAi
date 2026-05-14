# TradeMarketAi

TradeMarketAi è una piattaforma PHP/MySQL per consultare dati di mercato, gestire un portafoglio virtuale, configurare alert di prezzo e usare funzioni AI e probabilistiche per analisi più avanzate.

## Informazioni rapide

- Creatore: Teramo Ettore
- Target: investitori principianti e utenti esperti
- Tecnologie: HTML, CSS, JavaScript, PHP, MySQL
- Demo: https://trademarket-ai-pulse.lovable.app

## Documentazione

- [Analisi requisiti](ANALISI%20REQUISITI.md)
- [Casi d'uso testuali](CASI%20D'USO.md)
- [Diagrammi di progetto](docs/diagrammi.md)
- [Manuale utente](docs/manuale_utente.md)

## Test

La repository include test unitari PHP basati su PHPUnit per la logica più importante e deterministica: validazione registrazione, verifica permessi e JWT.

Per eseguire i test:

1. Installa le dipendenze con Composer.
2. Esegui `composer test`.

## Database locale

Usa il dump aggiornato [auth_system.sql](auth_system.sql). Include già:
- toggle admin per attivare/disattivare le transazioni;
- toggle admin per attivare/disattivare i cursori live;
- log `subscription_transactions`;
- errore DB `TRANSAZIONI_DISATTIVATE_DB` quando si tenta il cambio piano con transazioni disattivate.
