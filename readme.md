# Schema.org Structured Data

*Deutsch (Standardsprache) · [English version](README.en.md)*

Contao-Bundle, das für jede Frontend-Seite automatisch strukturierte Daten als
JSON-LD erzeugt und als zusammenhängenden `@graph` direkt vor `</head>`
einfügt. Ziel sind Suchmaschinen und KI-Antwortmaschinen, die strukturierte
Daten für Rich Results und Zitationen auswerten.

## Inhalt

- [Projektübersicht](#projektübersicht)
- [Status](#status)
- [Unterstützte Versionen](#unterstützte-versionen)
- [Systemvoraussetzungen](#systemvoraussetzungen)
- [Installation](#installation)
- [Lizenz aktivieren](#lizenz-aktivieren)
- [Backend](#backend)
- [Konfiguration](#konfiguration)
- [Ausgabe im Frontend](#ausgabe-im-frontend)
- [Funktionsübersicht](#funktionsübersicht)
- [Berechtigungen](#berechtigungen)
- [Lizenz- und Berechtigungsverhalten](#lizenz--und-berechtigungsverhalten)
- [Sicherheitsmodell](#sicherheitsmodell)
- [Betriebssicherheit](#betriebssicherheit)
- [Laufzeitverzeichnisse](#laufzeitverzeichnisse)
- [Externe Kommunikation](#externe-kommunikation)
- [Protokollierung](#protokollierung)
- [Deployment](#deployment)
- [Cache leeren](#cache-leeren)
- [Tests](#tests)
- [Fehlerbehebung](#fehlerbehebung)
- [Bekannte Einschränkungen](#bekannte-einschränkungen)
- [Lizenz und Urheberrecht](#lizenz-und-urheberrecht)
- [Weiterführende Dokumente](#weiterführende-dokumente)

## Projektübersicht

Das Bundle baut je Seitenaufruf einen einzelnen, quervernetzten JSON-LD-Graphen
auf. Die Knoten verweisen über `@id` aufeinander, statt mehrere unabhängige
`<script>`-Blöcke auszugeben.

Beigetragen werden, abhängig von Konfiguration und installierten Contao-Bundles:

| Knoten | Quelle |
|---|---|
| `Organization` oder `LocalBusiness` | Startseite (Adresse, Logo, Kontaktdaten, Profil-URLs) |
| `WebSite` mit `SearchAction` | Startseite (optional, mit Such-URL) |
| `WebPage` und Untertypen | Seite (Titel, Beschreibung, Bild, `speakable`) |
| `BreadcrumbList` | Seitenpfad der aktuellen Seite |
| `NewsArticle`, `Article`, `BlogPosting` | Nachrichten-Detailseite |
| `Event` | Termin-Detailseite |
| `FAQPage` mit `Question`/`Answer` | FAQ-Leseseite |
| Eigene Knoten | Frei eingegebenes JSON-LD je Seite oder Datensatz |

Die Einfügung erfolgt bewusst über die Antwort und nicht über `TL_HEAD`: eigene
`fe_page`-Templates lassen den `TL_HEAD`-Insert-Tag häufig weg, wodurch die
Ausgabe unbemerkt verloren ginge.

## Status

Produktiv einsetzbar. Version 2.0.0 ist eine vollständige Neuimplementierung der
Lizenzprüfung; die Ausgabe strukturierter Daten ist gegenüber 1.x unverändert.

**Aktualisierung von 1.x:** Die frühere lokale Lizenzablage wird nicht
weiterverwendet. Ein vorhandener Schlüssel wird beim ersten Öffnen der
Einstellungen serverseitig übernommen und muss einmalig erneut aktiviert werden.
Bis dahin gibt das Bundle keine strukturierten Daten aus.

## Unterstützte Versionen

| Komponente | Unterstützt |
|---|---|
| Contao | 5.3 und neuer (`^5.3`, ältere Versionen ausgeschlossen) |
| PHP | 8.2, 8.3, 8.4 (`^8.2`) |
| Symfony | 6.4 und 7.x |
| Doctrine DBAL | 3.6 und neuer sowie 4.x |

## Systemvoraussetzungen

- Contao Managed Edition oder eine gleichwertige Symfony-Anwendung mit Contao 5.3+
- PHP-Erweiterungen: `json`, `sodium` (zwingend), `intl` (empfohlen)
- Ausgehende HTTPS-Verbindungen zum Lizenzserver
- Beschreibbares `var/`-Verzeichnis der Installation
- Erreichbarkeit der Installation über HTTPS unter einem echten Hostnamen

`sodium` ist Pflicht: ohne diese Erweiterung lässt sich keine Lizenz prüfen und
das Bundle bleibt inaktiv.

## Installation

Über den Contao Manager das Paket `vtinnovations/schema-org` hinzufügen, oder
auf der Kommandozeile:

```bash
composer require vtinnovations/schema-org
```

Das Paket muss dabei über Packagist oder ein in `composer.json` konfiguriertes
Repository erreichbar sein.

Anschließend Datenbank aktualisieren und Cache leeren:

```bash
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear
```

Die Migration legt die zusätzlichen Felder in `tl_page`, `tl_news`,
`tl_calendar_events` und `tl_faq` an.

### Dateisystemberechtigungen

Das Bundle legt unterhalb von `var/` ein eigenes Arbeitsverzeichnis an und
erwartet dort Schreibrechte für den Webserver- und den CLI-Benutzer. Es wird mit
eingeschränkten Rechten erzeugt (Verzeichnis `0700`, Dateien `0600`) und darf
nicht über den Webserver erreichbar sein. Das Verzeichnis gehört nicht in die
Versionsverwaltung.

Restriktive `open_basedir`- oder `umask`-Einstellungen müssen den Zugriff auf
`var/` erlauben.

## Lizenz aktivieren

Die Lizenz wird unter **System → Einstellungen** im Abschnitt
**Schema.org Licence management** verwaltet. Das ist die einzige Stelle im
Backend, an der Lizenzdaten sichtbar sind oder verändert werden können.

1. Auf der Startseite (Seitentyp „Startpunkt einer Webseite“) im Feld **Domainname**
   die Domain eintragen, unter der die Website erreichbar ist.
2. Cache leeren, damit die Änderung wirksam wird.
3. **System → Einstellungen** öffnen, den Lizenzschlüssel eingeben und
   **Lizenz prüfen und aktivieren** wählen.

Nach erfolgreicher Aktivierung zeigt der Abschnitt den maskierten Schlüssel, das
Paket, die Laufzeit, den aktiven Hostnamen, alle lizenzierten Hostnamen und die
Lizenzversion.

Weitere Schaltflächen:

- **Lizenz aktualisieren** – holt den aktuellen Stand vom Lizenzserver. Schlägt
  der Aufruf fehl, bleibt die bisherige Lizenz unverändert bestehen.
- **Lizenz entfernen** – erfordert das Setzen des Bestätigungshakens und
  versetzt die Installation sofort in den unlizenzierten Zustand.

Der vollständige Schlüssel wird nach der Aktivierung nicht mehr im Backend
angezeigt und nicht in das Eingabefeld zurückgeschrieben.

Ausführliche Beschreibung: [Documentation/Lizenzierung.md](Documentation/Lizenzierung.md)

## Backend

| Ort | Inhalt |
|---|---|
| **Schema.org → Schema.org** | Vorschau des erzeugten JSON-LD je Seite, mit Verweisen auf den Google Rich Results Test und den schema.org-Validator |
| **System → Einstellungen** | Abschnitt **Schema.org Licence management** |
| **Seitenstruktur → Startpunkt einer Webseite** | Abschnitt **Schema.org / Strukturierte Daten** (websiteweite Angaben) |
| **Seitenstruktur → sonstige Seite** | Abschnitt **Schema.org / Strukturierte Daten** (Übersteuerungen je Seite) |
| **Nachrichten, Termine, FAQ** | Abschnitt **Schema.org** je Datensatz |

Das Modul **Schema.org** ist reine Anzeige: Es erzeugt keine Daten, speichert
nichts und enthält keine Lizenzfunktionen. Ohne gültige Lizenz zeigt es einen
Hinweis mit Verweis auf die Einstellungen.

## Konfiguration

### Startseite – websiteweite Angaben

| Feld | Wirkung |
|---|---|
| Schema.org komplett deaktivieren | Schaltet die Ausgabe für die gesamte Website ab |
| Organisationstyp | `Organization`, `Lokales Unternehmen` oder keine Ausgabe |
| Name der Organisation | Leer: Titel der Startseite |
| Logo | Wird als `logo`/`image` ausgegeben |
| sameAs (Profil-URLs) | Social-Media-, Wikipedia- oder Profil-URLs |
| Telefon, E-Mail | Zusätzlich als `ContactPoint` |
| Adresse, Region, Ländercode | `PostalAddress` |
| Breiten- und Längengrad | `GeoCoordinates`, nur für lokale Unternehmen sinnvoll |
| Öffnungszeiten, Preisniveau | Nur für lokale Unternehmen sinnvoll |
| WebSite-Knoten ausgeben | Aktiviert den `WebSite`-Knoten |
| Such-URL (SearchAction) | Aktiviert die Suchbox-Auszeichnung |

### Einzelseite – Übersteuerungen

| Feld | Wirkung |
|---|---|
| Schema für diese Seite deaktivieren | Unterdrückt die gesamte Ausgabe dieser Seite |
| WebPage-Typ | Genauerer Seitentyp, etwa Kontaktseite oder FAQ-Seite |
| Speakable CSS-Selektoren | Markiert vorlesbare Bereiche |
| Eigenes JSON-LD | Zusätzliche Knoten für den Graphen |

### Nachrichten, Termine, FAQ

| Datensatz | Felder |
|---|---|
| Nachricht | Schema deaktivieren, Article-Typ, Autor (Name und URL), Speakable, eigenes JSON-LD |
| Termin | Schema deaktivieren, Event-Status, Teilnahmeart, Veranstaltungsort, eigenes JSON-LD |
| FAQ | Aus FAQPage ausschließen |

Eigenes JSON-LD wird ohne `@context` erwartet; ein mitgegebener `@context` wird
entfernt. Ungültiges JSON wird stillschweigend übergangen und bricht die Seite
nicht ab.

## Ausgabe im Frontend

Ausgegeben wird ausschließlich, wenn alle folgenden Punkte zutreffen:

- gültige Lizenz für den aufgerufenen Hostnamen,
- Frontend-Anfrage mit aufgelöster Seite,
- Antwort ist HTML und enthält `</head>`,
- weder die Website noch die Seite ist deaktiviert,
- mindestens ein Knoten wurde erzeugt.

Trifft eine Bedingung nicht zu, verhält sich Contao exakt wie ohne dieses
Bundle. Es werden keine Inhalte verändert, entfernt oder umgeleitet.

## Funktionsübersicht

| Funktion | Status |
|---|---|
| JSON-LD-Ausgabe im Frontend | Verfügbar |
| Organization / LocalBusiness | Verfügbar |
| WebSite mit SearchAction | Verfügbar |
| WebPage mit Speakable-Auszeichnung | Verfügbar |
| BreadcrumbList | Verfügbar |
| NewsArticle / Article / BlogPosting | Bedingt (benötigt `contao/news-bundle`) |
| Event | Bedingt (benötigt `contao/calendar-bundle`) |
| FAQPage | Bedingt (benötigt `contao/faq-bundle`) |
| Eigenes JSON-LD je Seite und Datensatz | Verfügbar |
| Backend-Vorschau mit Validator-Verweisen | Verfügbar |
| Lizenzverwaltung im Backend | Verfügbar |
| Tägliche Lizenzaktualisierung | Verfügbar (über den Contao-Cron) |
| Vom Lizenzserver ausgelöste Aktualisierung | Verfügbar |
| Oberfläche auf Deutsch und Englisch | Verfügbar |
| Getrennte Lizenzstufen Free und Pro | Nicht zutreffend (das Produkt hat eine Stufe) |

## Berechtigungen

| Bereich | Voraussetzung |
|---|---|
| Modul **Schema.org** | Zugriff auf das Backend-Modul in der Benutzergruppe |
| Abschnitt **Schema.org Licence management** | Zugriff auf das Modul **Einstellungen**; in Contao standardmäßig Administratoren vorbehalten |
| Felder auf Seiten und Datensätzen | Contao behandelt diese Felder als geschützt: Benutzer ohne Administratorrechte benötigen sie in ihrer Gruppe unter den erlaubten Feldern |

Jede lizenzverändernde Aktion prüft serverseitig zuerst die Modulberechtigung
und anschließend das Contao-Anfrage-Token, bevor irgendetwas geschieht.

## Lizenz- und Berechtigungsverhalten

Das Produkt wird als dauerhaft kostenfreie Ausgabe verteilt, benötigt aber eine
ausgestellte und aktivierte Lizenz. Es gibt keinen anonymen Betrieb, keinen
automatisch startenden Testzeitraum und keine lokal erzeugbare Lizenz.

| Zustand | Sichtbares Verhalten |
|---|---|
| Keine Lizenz aktiviert | Keine Ausgabe strukturierter Daten; Verwaltung und Konfiguration bleiben nutzbar |
| Lizenz aktiv | Vollständige Ausgabe für die lizenzierten Hostnamen |
| Lizenz gilt nicht für diese Installation | Keine Ausgabe; der Abschnitt weist darauf hin |
| Lizenz entfernt | Sofort wieder wie ohne Lizenz |

Die Zuordnung erfolgt exakt je Hostname. `example.com` und `www.example.com`
sind unterschiedliche Hosts; beide müssen lizenziert sein, wenn die Website
unter beiden erreichbar ist.

Gespeicherte Konfiguration bleibt in jedem Zustand erhalten. Wird eine Lizenz
später wieder aktiviert, sind alle Einstellungen unverändert vorhanden.

## Sicherheitsmodell

- Lizenzentscheidungen fallen serverseitig. Die Ausgabe wird an mehreren
  Stellen unabhängig voneinander geprüft, nicht an einer zentralen Stelle
  freigeschaltet.
- Lizenzdaten liegen außerhalb des öffentlichen Verzeichnisses mit
  eingeschränkten Dateirechten.
- Empfangene Lizenzdaten werden auf Echtheit und Unverändertheit geprüft,
  bevor sie übernommen werden. Nachträglich veränderte lokale Daten werden
  erkannt und führen zum unlizenzierten Zustand.
- Der Austausch mit dem Lizenzserver erfolgt ausschließlich über feste
  HTTPS-Adressen mit Zertifikatsprüfung, ohne Weiterleitungen und mit
  begrenzten Zeitlimits und Antwortgrößen.
- Der öffentlich erreichbare Endpunkt für serverseitig ausgelöste
  Aktualisierungen akzeptiert nur kryptografisch authentifizierte Anfragen.
  Herkunftsangaben wie `Origin`, `Referer` oder Absender-IP gelten nicht als
  Nachweis.
- Wiederholt eingespielte oder veraltete Aktualisierungen werden abgewiesen;
  eine ältere Lizenz kann eine neuere nicht ersetzen.
- Das Paket enthält keinerlei Signaturschlüssel und keine wiederverwendbaren
  Zugangsdaten.
- Es gibt keine Umgebungsvariable und keine Einstellung, mit der sich die
  Prüfung abschalten lässt.

Ausführliche Darstellung: [Documentation/Sicherheit.md](Documentation/Sicherheit.md)

Kein Schutzmechanismus ist unumgehbar. Die beschriebenen Maßnahmen erschweren
Manipulation und machen sie erkennbar; sie sind keine Garantie.

## Betriebssicherheit

- Änderungen am Lizenzzustand erfolgen unter einer Sperre und werden erst
  wirksam, nachdem das Ergebnis erneut eingelesen und geprüft wurde. Schlägt
  diese Nachprüfung fehl, wird der vorherige Zustand zurückgeschrieben.
- Ein nicht erreichbarer Lizenzserver, eine Zeitüberschreitung oder eine
  fehlerhafte Antwort verändern eine funktionierende Lizenz nicht.
- Fehler einzelner Knotenlieferanten werden abgefangen; eine Seite wird dadurch
  nie unvollständig ausgeliefert oder abgebrochen.
- Das Bundle löscht, verschiebt und überschreibt keine Inhalte, Dateien oder
  Einstellungen der Installation.

## Laufzeitverzeichnisse

| Pfad | Inhalt |
|---|---|
| `var/schema-org/` | Lizenzzustand und Verwaltungsdaten des Bundles |

Das Verzeichnis muss beschreibbar, privat und von Sicherungen erfasst sein. Bei
mehreren Anwendungsservern muss es auf gemeinsam genutztem Speicher liegen,
damit Sperren und Wiederholungsschutz installationsweit greifen.

## Externe Kommunikation

Das Bundle kommuniziert ausschließlich mit dem Lizenzdienst von
V&T Innovations unter `www.v-t.one` über HTTPS. Die Adressen sind fest im Code
hinterlegt und lassen sich nicht per Konfiguration umlenken.

| Anlass | Richtung | Übertragene Angaben |
|---|---|---|
| Lizenz aktivieren oder aktualisieren | Installation → Lizenzdienst | Produktkennung, Hostname, Lizenzschlüssel |
| Nutzungsmeldung | Installation → Lizenzdienst | Produktname und Hostname, je Seitenaufruf mit ausgegebenen Daten, nach dem Ausliefern der Antwort |
| Sitzungsmeldung | Installation → Lizenzdienst | Hostname und Lizenzschlüssel, einmal je angemeldeter Backend-Sitzung beim ersten Öffnen des Lizenzabschnitts |
| Ausgelöste Aktualisierung | Lizenzdienst → Installation | Aktualisierte Lizenzdaten an den öffentlichen Endpunkt des Bundles |

Meldungen werden erst nach dem Senden der Antwort ausgeführt und beeinflussen
weder die Auslieferung noch die Lizenzgültigkeit. Ein Fehlschlag bleibt folgenlos
und wird innerhalb derselben Sitzung nicht wiederholt.

Für Firewalls: ausgehend muss `www.v-t.one` über HTTPS erreichbar sein.
Eingehend muss der öffentliche Aktualisierungs-Endpunkt des Bundles erreichbar
bleiben. Sein genauer Pfad lässt sich in der Installation ermitteln:

```bash
vendor/bin/contao-console debug:router | grep vtinnovations
```

## Protokollierung

Protokolliert werden ausschließlich betriebliche Angaben: Vorgang, technische
Anfragekennung, HTTP-Status, Dauer, Ergebniskategorie und die übernommene
Lizenzversion.

Nicht protokolliert werden Lizenzschlüssel, Prüfsummen, Signaturen, übertragene
Daten, Antwortinhalte oder daraus abgeleitete Werte. Der Lizenzschlüssel
erscheint weder in der Browserausgabe noch in Fehlermeldungen oder Diagnosen.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/contao-console contao:migrate --no-interaction
vendor/bin/contao-console cache:clear --env=prod
vendor/bin/contao-console cache:warmup --env=prod
```

Zusätzlich sicherstellen:

- `var/schema-org/` ist beschreibbar und wird nicht zwischen Installationen kopiert,
- der Contao-Cron läuft (Weberoberfläche oder `contao:cron`),
- ausgehende HTTPS-Verbindungen sind erlaubt,
- bei Reverse-Proxy-Betrieb sind `trusted_proxies` und `trusted_hosts` in
  Symfony korrekt gesetzt, da der Hostname der Installation daraus abgeleitet wird.

Der Lizenzzustand ist an den Hostnamen gebunden. Beim Klonen einer Installation
auf eine andere Domain wird die kopierte Lizenz dort nicht anerkannt; sie muss
für den neuen Hostnamen ausgestellt werden.

## Cache leeren

```bash
vendor/bin/contao-console cache:clear
vendor/bin/contao-console cache:clear --env=prod
```

Im Contao Manager steht dafür ebenfalls die Funktion zum Leeren des
Anwendungs-Caches zur Verfügung.

## Tests

Das Paket bringt eine Testsuite mit. Mit installierten
Entwicklungsabhängigkeiten läuft sie über PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

Für Umgebungen ohne Entwicklungsabhängigkeiten steht ein eigenständiger
Testlauf bereit, der nur PHP benötigt:

```bash
composer test-standalone
```

Erstellen eines auslieferbaren Pakets samt Prüfungen und SHA-256-Manifest:

```bash
composer build-release
```

Der Erstellungslauf bricht ab, wenn die Testsuite fehlschlägt oder das Paket
keine gültigen Prüfdaten enthält.

## Fehlerbehebung

| Beobachtung | Ursache und Abhilfe |
|---|---|
| Kein JSON-LD im Quelltext | Lizenz prüfen; Startseite oder Seite deaktiviert; Antwort ist kein HTML oder enthält kein `</head>` |
| „Auf keiner Startseite ist eine Domain hinterlegt …“ | Auf der Startseite den Domainnamen eintragen und Cache leeren |
| „Diese Lizenz ist für keine der auf dieser Installation konfigurierten Domains ausgestellt.“ | Hostname der Lizenz und Domainname der Startseite stimmen nicht exakt überein, etwa `example.com` gegenüber `www.example.com` |
| „Der Lizenzserver war nicht erreichbar“ | Ausgehende HTTPS-Verbindungen zu `www.v-t.one` freigeben; die bisherige Lizenz bleibt bestehen |
| Aktivierung schlägt trotz korrektem Schlüssel fehl | `sodium` prüfen, Systemzeit prüfen, Schreibrechte auf `var/` prüfen |
| Ausgabe verschwindet nach Umzug oder Domainwechsel | Lizenz ist hostgebunden und muss für den neuen Hostnamen ausgestellt werden |
| Nachrichten, Termine oder FAQ ohne Knoten | Das jeweilige Contao-Bundle ist nicht installiert, oder der Datensatz ist nicht veröffentlicht |
| Eigenes JSON-LD erscheint nicht | Ungültiges JSON wird übergangen; Eingabe ohne `@context` prüfen |

## Bekannte Einschränkungen

- Installationen unter einer IP-Adresse oder einem einteiligen Hostnamen wie
  `localhost` können nicht lizenziert werden und geben keine strukturierten
  Daten aus.
- Die Zuordnung erfolgt exakt je Hostname; Unterdomains und die Form mit `www`
  sind eigenständige Hosts.
- Ist auf keiner Startseite ein Domainname hinterlegt, wird der aktuelle, von
  Symfony geprüfte Hostname der Anfrage verwendet. Für ein vorhersagbares
  Verhalten sollte der Domainname gesetzt sein.
- Bei mehreren Anwendungsservern ohne gemeinsam genutztes `var/` wirken Sperre
  und Wiederholungsschutz nur je Server.
- Auf lizenzierten Seiten wird nach dem Ausliefern der Antwort je Aufruf eine
  Nutzungsmeldung gesendet. Auf stark frequentierten Websites ist das bei der
  Kapazitätsplanung zu berücksichtigen.
- Eigenes JSON-LD wird nicht gegen schema.org validiert.
- Die Backend-Vorschau erzeugt für einzelne Seitentypen keine absolute URL und
  kann diese daher nicht darstellen.
- Es gibt genau eine Lizenzstufe. Eine Unterscheidung zwischen Free und Pro
  existiert in diesem Produkt nicht.

## Lizenz und Urheberrecht

Paket: `vtinnovations/schema-org`
Lizenz: `LGPL-3.0-or-later`
Copyright: V&T Innovations

Die Softwarelizenz nach LGPL und die produktseitige Lizenzierung sind zwei
verschiedene Dinge: Die LGPL regelt die Rechte am Quellcode, der Lizenzschlüssel
schaltet die Ausgabe strukturierter Daten für einen bestimmten Hostnamen frei.

## Weiterführende Dokumente

- [English version](README.en.md)
- [Documentation/Lizenzierung.md](Documentation/Lizenzierung.md) – Lizenzverwaltung im Detail
- [Documentation/Sicherheit.md](Documentation/Sicherheit.md) – Sicherheitsmodell
