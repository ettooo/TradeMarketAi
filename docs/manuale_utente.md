# Manuale utente

## Panoramica

TradeMarketAi è una piattaforma PHP/MySQL per consultare dati di mercato, gestire un portafoglio virtuale, impostare alert di prezzo e, per gli account Premium, accedere a funzioni avanzate di analisi AI e simulazione.

## Accesso e registrazione

1. Apri la pagina di registrazione.
2. Inserisci username, email e password.
3. Usa una password di almeno 8 caratteri, con almeno una maiuscola e un numero.
4. Dopo la registrazione, accedi dalla pagina di login.
5. Il login accetta email o username e password.

Se l'account è disabilitato, l'accesso viene negato.

## Dashboard

La dashboard è la schermata centrale dell'applicazione. Da qui puoi:
- vedere i dati di mercato disponibili per il tuo ruolo;
- consultare il tuo portafoglio virtuale;
- gestire gli alert di prezzo;
- aprire il profilo;
- uscire dall'applicazione.

### Sezioni principali

- **Mercato**: mostra simbolo, nome, prezzo, variazione percentuale, volume e market cap.
- **Portafoglio**: mostra i portafogli disponibili e le posizioni inserite.
- **Alert**: mostra gli alert già configurati sul tuo account.
- **Amministrazione**: visibile solo agli amministratori con i permessi adeguati.

## Stato implementazione

Le sezioni Registrazione/Login, Profilo, Mercato, Portafoglio, Alert e Amministrazione base sono già operative.

Le funzioni Premium di analisi predittiva AI e simulazioni probabilistiche sono previste dal progetto e dai diagrammi, ma nel codice attuale restano ancora TODO: la dashboard espone i permessi corrispondenti, ma non offre ancora un pannello operativo dedicato.

## Profilo

La pagina profilo permette di consultare email e ruolo, e di cambiare username se il tuo account ha il permesso `edit_profile`.

Per aggiornare lo username:
1. Apri la pagina profilo.
2. Inserisci il nuovo username.
3. Conferma il salvataggio.

Il nuovo username deve essere univoco e lungo tra 3 e 50 caratteri.

## Portafoglio virtuale

Il portafoglio virtuale è pensato per simulare l'andamento degli investimenti.

Funzionalità disponibili:
- visualizzazione del portafoglio principale;
- elenco delle posizioni;
- calcolo del capitale investito per singola posizione.

## Alert di prezzo

Gli alert servono a monitorare un titolo quando supera o scende sotto una soglia impostata.

Puoi usare alert base se hai il permesso `set_basic_alerts`. Gli account Premium possono usare anche funzionalità avanzate con il permesso `set_advanced_alerts`.

## Funzioni Premium

Gli account Premium sbloccano:
- dati di mercato avanzati;
- analisi predittive AI;
- simulazioni probabilistiche;
- alert avanzati;
- funzionalità aggiuntive sul portafoglio.

## Funzioni Admin

L'account amministratore può accedere alla sezione amministrativa della dashboard per:
- visualizzare utenti e ruoli;
- controllare alcune impostazioni del sistema;
- monitorare sessioni attive e report, se i permessi sono presenti.

## Uscita

Per chiudere la sessione, usa il pulsante di logout. L'applicazione revoca il refresh token associato e reindirizza alla pagina di login.

## Suggerimenti operativi

- Se la dashboard mostra un errore di permesso, verifica di aver effettuato l'accesso con il ruolo corretto.
- Se il login fallisce, controlla email/username e password.
- Se l'upgrade di piano non è disponibile, la causa più probabile è la disattivazione delle transazioni lato database.