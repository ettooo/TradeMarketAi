# **Obiettivo del Sito**
Fornire una piattaforma web che consenta agli utenti di:
- monitorare i mercati azionari,
- visualizzare dati e indici in tempo reale,
- analizzare l’andamento delle borse,
- ricevere analisi predittive basate su intelligenza artificiale,
- ottenere simulazioni e strategie per cercare di battere il mercato.

---

# **Attori Principali**
- **Utente** (registrato o ospite)  
- **Sistema AI**  
- **Amministratore**  
- **Fonti dati esterne (API finanziarie)**

---

# **Casi d’Uso Principali**

---

## **CU1 – Registrazione e autenticazione utente**
**Descrizione:** L’utente può registrarsi, effettuare il login e gestire il proprio account.  
**Attori:** Utente  
**Precondizione:** L’utente non è autenticato  

### **Flusso:**
1. Compilazione del modulo di registrazione  
2. Verifica email  
3. Accesso tramite credenziali  

**Postcondizione:** L’utente è autenticato e ha accesso alle funzionalità personalizzate.

---

## **CU2 – Visualizzazione andamento dei mercati**
**Descrizione:** L’utente consulta l’andamento in tempo reale o storico di indici e titoli.  
**Attori:** Utente  
**Precondizione:** L’utente è autenticato  

### **Flusso:**
1. Selezione di un indice, titolo o mercato  
2. Visualizzazione di grafici e dati (volumi, prezzi, variazioni)  
3. Possibilità di filtrare per periodo o settore  

**Postcondizione:** L’utente ottiene una panoramica aggiornata del mercato selezionato.

---

## **CU3 – Analisi predittiva tramite AI**
**Descrizione:** Il sistema AI fornisce previsioni basate su dati storici e di mercato.  
**Attori:** Utente, Sistema AI  
**Precondizione:** L’utente seleziona un titolo o indice  

### **Flusso:**
1. Richiesta di analisi predittiva  
2. Elaborazione della richiesta da parte dell’AI  
3. Visualizzazione dei risultati (grafici, probabilità, intervalli)  

**Postcondizione:** L’utente visualizza una stima dell’andamento futuro.

---

## **CU4 – Studio probabilistico per battere il mercato**
**Descrizione:** L’AI genera strategie ottimizzate per sovraperformare il mercato.  
**Attori:** Utente, Sistema AI  
**Precondizione:** Inserimento dei parametri di simulazione  

### **Flusso:**
1. Input dei parametri (capitale, rischio, periodo…)  
2. Simulazione (Monte Carlo, backtesting…)  
3. Visualizzazione dei risultati  

**Postcondizione:** L’utente riceve strategie probabilistiche ottimizzate.

---

## **CU5 – Notifiche e alert di mercato**
**Descrizione:** Il sistema invia notifiche basate su soglie o segnali AI.  
**Attori:** Utente, Sistema AI  
**Precondizione:** L’utente ha configurato alert  

### **Flusso:**
1. Impostazione delle preferenze  
2. Monitoraggio automatico  
3. Invio notifiche via email, push o in-app  

**Postcondizione:** L’utente è aggiornato sugli eventi rilevanti.

---

## **CU6 – Consultazione della dashboard personalizzata**
**Descrizione:** Vista personalizzabile con dati e strumenti.  
**Attori:** Utente  

### **Flusso:**
1. Accesso alla dashboard  
2. Personalizzazione dei widget  
3. Consultazione delle analisi  

**Postcondizione:** L’utente ottiene una vista centralizzata personalizzata.

---

## **CU7 – Gestione di un portafoglio virtuale**
**Descrizione:** L’utente può simulare investimenti.  
**Attori:** Utente  

### **Flusso:**
1. Aggiunta dei titoli  
2. Simulazione di acquisti/vendite  
3. Analisi delle performance  

**Postcondizione:** Portafoglio aggiornato e performance analizzabili.

---

## **CU8 – Gestione utenti e sistema (Admin)**
**Descrizione:** L’amministratore gestisce utenti, contenuti e modelli AI.  
**Attori:** Amministratore  

### **Flusso:**
1. Login amministratore  
2. Gestione account utente  
3. Monitoraggio modelli e fonti dati  
4. Gestione contenuti pubblici  

**Postcondizione:** Il sistema è mantenuto correttamente.

---

# **Casi d’Uso Secondari o Estendibili**

## **CU9 – Forum o community di discussione**
L’utente può partecipare a discussioni e confrontare strategie.

## **CU10 – Integrazione con broker esterni**
L’utente può collegare il proprio account di trading e analizzare il portafoglio reale.
