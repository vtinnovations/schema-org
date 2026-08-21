# Lizenzierung

*Deutsch (Standardsprache) · [English version](Licensing.en.md) · [Zurück zur Übersicht](../README.md)*

Dieses Dokument beschreibt die Lizenzverwaltung aus Sicht einer Administratorin
oder eines Administrators.

## Grundprinzip

Schema.org Structured Data wird als dauerhaft kostenfreie Ausgabe verteilt,
benötigt aber eine von V&T Innovations ausgestellte und aktivierte Lizenz. Ohne
gültige Lizenz gibt das Bundle keine strukturierten Daten aus; Contao selbst
bleibt davon unberührt.

Es gibt bewusst nicht:

- einen anonymen Betrieb ohne Schlüssel,
- einen Testzeitraum, der sich durch die Installation selbst startet,
- eine lokal erzeugbare oder importierbare Lizenz,
- eine Einstellung oder Umgebungsvariable, die die Prüfung abschaltet.

Die Lizenz gilt für einen oder mehrere genau benannte Hostnamen.

## Voraussetzungen

| Voraussetzung | Bedeutung |
|---|---|
| PHP-Erweiterung `sodium` | Ohne sie kann keine Lizenz geprüft werden |
| Domainname auf der Startseite | Legt fest, für welchen Hostnamen aktiviert wird |
| Ausgehendes HTTPS zu `www.v-t.one` | Wird für Aktivierung und Aktualisierung benötigt |
| Beschreibbares `var/`-Verzeichnis | Speicherort des Lizenzzustands |
| Zugriff auf **System → Einstellungen** | In Contao standardmäßig Administratoren vorbehalten |

Installationen unter einer IP-Adresse oder einem einteiligen Hostnamen wie
`localhost` lassen sich nicht lizenzieren.

## Lizenz aktivieren

1. **Seitenstruktur** öffnen, den Startpunkt der Webseite bearbeiten und unter
   **Domainname** die Domain eintragen, unter der die Website erreichbar ist.
2. Cache leeren.
3. **System → Einstellungen** öffnen und zum Abschnitt
   **Schema.org Licence management** blättern.
4. Den Lizenzschlüssel in das Feld **Lizenzschlüssel** eingeben.
5. **Lizenz prüfen und aktivieren** wählen.

Die Anfrage wird serverseitig ausgeführt. Der Browser nimmt keine Verbindung zum
Lizenzdienst auf und sieht den Schlüssel nach dem Absenden nicht wieder.

Nach erfolgreicher Aktivierung meldet Contao „Die Lizenz wurde aktiviert.“ und
der Abschnitt zeigt:

| Angabe | Bedeutung |
|---|---|
| Schlüssel | Maskiert, nur erste und letzte Zeichen |
| Paket | Ausgestellte Lizenzstufe |
| Laufzeit | Bei diesem Produkt unbegrenzt |
| Aktiv für | Hostname, unter dem die Installation gerade lizenziert arbeitet |
| Lizenzierte Hosts | Alle von der Lizenz abgedeckten Hostnamen |
| Lizenzversion | Fortlaufende Version des Lizenzstands |

## Lizenz aktualisieren

**Lizenz aktualisieren** holt den aktuellen Stand vom Lizenzdienst, etwa nach
einer Verlängerung oder nach Aufnahme weiterer Hostnamen. Dabei wird der
gespeicherte Schlüssel verwendet; ein abweichender Schlüssel im Eingabefeld
ersetzt ihn.

Scheitert der Aufruf, bleibt die bisherige Lizenz unverändert bestehen. Eine
Störung des Lizenzdienstes führt nicht zum Verlust der Lizenz.

## Lizenz entfernen

**Lizenz entfernen** erfordert das Setzen des Hakens
**Ja, Lizenz von dieser Installation entfernen**. Der Lizenzzustand wird
daraufhin gelöscht und die Installation verhält sich unmittelbar wieder wie eine
unlizenzierte.

Konfigurationsdaten auf Seiten und Datensätzen bleiben vollständig erhalten.
Nach einer erneuten Aktivierung stehen alle Einstellungen unverändert zur
Verfügung.

## Automatische Aktualisierung

Das Bundle meldet sich einmal täglich über den Contao-Cron beim Lizenzdienst,
sofern der gespeicherte Stand älter als zwölf Stunden ist. Damit werden
Änderungen wirksam, ohne dass jemand den Schlüssel erneut eingibt.

Voraussetzung ist ein laufender Contao-Cron, entweder über die eingebaute
Weberoberfläche oder über:

```bash
vendor/bin/contao-console contao:cron
```

Zusätzlich kann der Lizenzdienst eine Aktualisierung von sich aus an die
Installation senden. Dafür stellt das Bundle einen öffentlichen Endpunkt bereit,
der ausschließlich kryptografisch authentifizierte Anfragen annimmt. Der Pfad
lässt sich in der Installation ermitteln:

```bash
vendor/bin/contao-console debug:router | grep vtinnovations
```

Ist der Endpunkt durch eine Firewall oder eine WAF blockiert, funktionieren
Aktivierung und tägliche Aktualisierung weiterhin; nur vom Lizenzdienst
angestoßene Aktualisierungen erreichen die Installation dann nicht.

## Zustände

| Zustand | Sichtbares Verhalten | Anzeige im Abschnitt |
|---|---|---|
| Keine Lizenz aktiviert | Keine strukturierten Daten | „Keine Lizenz aktiviert.“ |
| Lizenz aktiv | Vollständige Ausgabe für die lizenzierten Hostnamen | „Lizenz aktiv.“ mit Detailangaben |
| Lizenz gilt nicht für diese Installation | Keine strukturierten Daten | Hinweis, dass die Lizenz für keine konfigurierte Domain ausgestellt ist |
| Lizenzdaten nachträglich verändert | Keine strukturierten Daten | Hinweis, dass die gespeicherte Lizenz diese Installation nicht berechtigt |
| Schlüssel aus Version 1.x gefunden | Keine strukturierten Daten bis zur Aktivierung | Hinweis mit Schaltfläche zur einmaligen Aktivierung |

Andere Lizenzstufen existieren in diesem Produkt nicht. Es gibt keinen
Testzeitraum, keine Pro-Stufe und keinen Ablauf mit Rückfall auf eine
eingeschränkte Stufe.

## Berechtigungen

| Funktion | Ohne Lizenz | Mit aktiver Lizenz |
|---|---|---|
| Strukturierte Daten im Frontend | Nicht verfügbar | Verfügbar |
| Vorschau im Modul **Schema.org** | Nicht verfügbar | Verfügbar |
| Konfigurationsfelder auf Seiten und Datensätzen | Verfügbar | Verfügbar |
| Lizenzabschnitt in den Einstellungen | Verfügbar | Verfügbar |

Konfiguration und Verwaltung bleiben immer erreichbar, damit eine Lizenz
überhaupt eingetragen werden kann und gespeicherte Angaben nicht verloren gehen.

## Zuordnung zu Hostnamen

Die Prüfung vergleicht Hostnamen zeichengenau. Groß- und Kleinschreibung, ein
abschließender Punkt, eine Portangabe und internationalisierte Schreibweisen
werden zuvor vereinheitlicht, ohne den Hostnamen inhaltlich zu verändern.

Daraus folgt:

- `example.com` und `www.example.com` sind verschiedene Hosts.
- `shop.example.com` ist von einer Lizenz für `example.com` nicht abgedeckt.
- Platzhalter wie `*.example.com` sind nicht zulässig.

Eine Installation gilt als lizenziert, wenn mindestens ein auf der Startseite
hinterlegter Domainname genau einem lizenzierten Hostnamen entspricht. Im
Frontend wird zusätzlich geprüft, ob der tatsächlich aufgerufene Hostname
lizenziert ist.

Ist auf keiner Startseite ein Domainname hinterlegt, wird der aktuelle, von
Symfony anhand von `trusted_hosts` und `trusted_proxies` geprüfte Hostname
verwendet. Für ein vorhersagbares Verhalten sollte der Domainname gesetzt sein.

## Aktualisierung von Version 1.x

Der frühere lokale Lizenzstand stammt aus einem anderen Verfahren und wird nicht
weiterverwendet. Beim ersten Öffnen der Einstellungen übernimmt das Bundle einen
dort gefundenen Schlüssel serverseitig, entfernt die alte Ablage und blendet die
Schaltfläche **Gefundenen Schlüssel der Vorversion aktivieren** ein.

Bis zur einmaligen Aktivierung gibt die Installation keine strukturierten Daten
aus.

## Übertragene Daten

| Anlass | Übertragene Angaben |
|---|---|
| Aktivierung und Aktualisierung | Produktkennung, Hostname, Lizenzschlüssel |
| Nutzungsmeldung | Produktname und Hostname, je Seitenaufruf mit ausgegebenen Daten |
| Sitzungsmeldung | Hostname und Lizenzschlüssel, einmal je angemeldeter Backend-Sitzung beim ersten Öffnen des Lizenzabschnitts |

Alle Meldungen laufen serverseitig, nach dem Ausliefern der Antwort und ohne
Auswirkung auf die Seitendarstellung. Es werden keine Inhalte der Website, keine
Besucherdaten und keine Benutzerkonten übertragen.

## Meldungen und ihre Bedeutung

| Meldung | Bedeutung |
|---|---|
| „Die Lizenz wurde aktiviert.“ | Aktivierung erfolgreich, Ausgabe ist aktiv |
| „Die Lizenz wurde aktualisiert.“ | Neuer Stand übernommen |
| „Die Lizenz wurde entfernt …“ | Installation ist wieder unlizenziert |
| „Der Lizenzserver war nicht erreichbar. Es wurde nichts geändert.“ | Netzwerk- oder Dienststörung; bisheriger Zustand unverändert |
| „Diese Lizenz ist für keine der auf dieser Installation konfigurierten Domains ausgestellt.“ | Hostname der Lizenz und Domainname der Startseite stimmen nicht exakt überein |
| „Auf keiner Startseite ist eine Domain hinterlegt …“ | Domainname auf der Startseite fehlt |
| „Die Lizenz konnte nicht aktiviert werden …“ | Schlüssel abgelehnt oder Antwort nicht verwertbar |
| „Bitte die Bestätigung ankreuzen …“ | Entfernen ohne gesetzten Haken |

Die Meldungen sind bewusst allgemein gehalten und nennen keine Prüfdetails.
Betriebliche Einzelheiten stehen im Anwendungsprotokoll.

## Sicherung, Wiederherstellung und Umzug

Der Lizenzzustand liegt unter `var/schema-org/`. Für den Betrieb gilt:

- Das Verzeichnis in Sicherungen einschließen.
- Es nicht in die Versionsverwaltung aufnehmen.
- Es nicht zwischen Installationen kopieren: der Zustand ist an Hostnamen
  gebunden und wird auf einer anderen Domain nicht anerkannt.
- Bei mehreren Anwendungsservern gemeinsam genutzten Speicher verwenden.

Beim Umzug auf eine andere Domain muss die Lizenz für den neuen Hostnamen
ausgestellt und dort neu aktiviert werden.

## Fehlerbehebung

| Beobachtung | Vorgehen |
|---|---|
| Abschnitt fehlt in den Einstellungen | Cache leeren; Zugriff auf das Modul **Einstellungen** prüfen |
| Aktivierung schlägt sofort fehl | Schlüssel auf Leer- und Sonderzeichen prüfen |
| Aktivierung meldet fehlende Domain | Domainname auf der Startseite eintragen und Cache leeren |
| Aktivierung meldet fehlenden Bezug zur Domain | Lizenzierten Hostnamen und Domainname der Startseite zeichengenau vergleichen |
| Aktivierung bricht ohne erkennbaren Grund ab | `sodium` prüfen, Systemzeit prüfen, Schreibrechte auf `var/` prüfen |
| Frontend zeigt trotz aktiver Lizenz nichts | Aufgerufenen Hostnamen mit den lizenzierten Hosts vergleichen; Kill-Switches auf Startseite und Seite prüfen |
| Lizenz nach Serverumzug ungültig | Neuen Hostnamen lizenzieren lassen |

## Weiterführende Dokumente

- [Zurück zur Übersicht](../README.md)
- [Sicherheitsmodell](Sicherheit.md)
- [English version](Licensing.en.md)
