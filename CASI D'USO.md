# Obiettivo del Sito
Fornire una piattaforma web che consenta agli utenti di:
- monitorare i mercati azionari,
- visualizzare dati e indici in tempo reale,
- analizzare l’andamento delle borse,
- ricevere analisi predittive basate su intelligenza artificiale,
- ottenere simulazioni e strategie per cercare di battere il mercato.

---

# Attori Principali
- Utente Free (registrato, piano base)
- Utente Premium (abbonato)
- Sistema AI
- Amministratore
- Fonti dati esterne (API finanziarie)

---

# Casi d’Uso Principali

---

## CU1 – Registrazione e autenticazione utente
**Descrizione:** L’utente può registrarsi, effettuare il login e gestire il proprio account.  
**Attori:** Utente Free, Utente Premium  
**Precondizione:** L’utente non è autenticato  

### Flusso:
1. Compilazione del modulo di registrazione  
2. Verifica email  
3. Accesso tramite credenziali  

**Postcondizione:**  
- L’utente è autenticato  
- Lo stato iniziale è Utente Free  

---

## CU2 – Visualizzazione andamento dei mercati
**Descrizione:** Consultazione di dati di mercato in tempo reale o storici.  
**Attori:** Utente Free, Utente Premium  
**Precondizione:** L’utente è autenticato  

### Flusso:
1. Selezione di un indice, titolo o mercato  
2. Visualizzazione di grafici e dati  
3. Filtro per periodo o settore  

**Postcondizione:**  
- Free: dati base e storico limitato  
- Premium: indicatori avanzati e storico esteso  

---

## CU3 – Analisi predittiva tramite AI
**Descrizione:** Il sistema AI genera previsioni sull’andamento futuro dei titoli.  
**Attori:** Utente Premium, Sistema AI  
**Precondizione:** Selezione di un titolo o indice  

### Flusso:
1. Richiesta di analisi predittiva  
2. Elaborazione della richiesta tramite AI  
3. Visualizzazione di probabilità, scenari e intervalli  

**Postcondizione:**  
- Premium: risultati completi  
- Free: funzionalità non disponibile  

---

## CU4 – Studio probabilistico per battere il mercato
**Descrizione:** Generazione di strategie di investimento ottimizzate.  
**Attori:** Utente Premium, Sistema AI  
**Precondizione:** Inserimento dei parametri di simulazione  

### Flusso:
1. Inserimento parametri (capitale, rischio, periodo)  
2. Simulazione (Monte Carlo, backtesting)  
3. Visualizzazione strategie e metriche  

**Postcondizione:**  
- Premium: strategie probabilistiche disponibili  
- Free: funzionalità non disponibile  

---

## CU5 – Notifiche e alert di mercato
**Descrizione:** Invio di notifiche automatiche sugli eventi di mercato.  
**Attori:** Utente Free, Utente Premium, Sistema AI  
**Precondizione:** Configurazione degli alert  

### Flusso:
1. Impostazione soglie o condizioni  
2. Monitoraggio automatico  
3. Invio notifiche (email, push, in-app)  

**Postcondizione:**  
- Free: alert su soglie di prezzo (limitati)  
- Premium: alert avanzati e segnali AI  

---

## CU6 – Consultazione della dashboard personalizzata
**Descrizione:** Visualizzazione centralizzata e personalizzabile dei dati.  
**Attori:** Utente Free, Utente Premium  

### Flusso:
1. Accesso alla dashboard  
2. Configurazione dei widget  
3. Consultazione delle informazioni  

**Postcondizione:**  
- Free: dashboard base con widget limitati  
- Premium: dashboard avanzata e completamente personalizzabile  

---

## CU7 – Gestione di un portafoglio virtuale
**Descrizione:** Simulazione di investimenti e analisi delle performance.  
**Attori:** Utente Free, Utente Premium  

### Flusso:
1. Aggiunta dei titoli  
2. Simulazione di acquisti e vendite  
3. Analisi delle performance  

**Postcondizione:**  
- Free: portafoglio con limiti operativi  
- Premium: portafogli multipli e analisi rischio avanzata  

---

## CU8 – Gestione utenti e sistema
**Descrizione:** Amministrazione completa della piattaforma.  
**Attori:** Amministratore  
**Precondizione:** Login amministratore  

### Flusso:
1. Gestione utenti e ruoli  
2. Monitoraggio modelli AI  
3. Gestione fonti dati e API  
4. Moderazione contenuti  

**Postcondizione:**  
- Sistema correttamente configurato e monitorato  

---

# Casi d’Uso Secondari o Estendibili

## CU9 – Forum o community di discussione
**Attori:** Utente Free, Utente Premium, Amministratore  
**Descrizione:** Discussione e condivisione di strategie di investimento.  

---

## CU10 – Integrazione con broker esterni
**Attori:** Utente Premium, Amministratore, API Broker  
**Descrizione:** Collegamento di account di trading reali per analisi avanzate.  
**Note:** Funzionalità riservata agli utenti Premium.
