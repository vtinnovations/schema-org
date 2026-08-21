# Sicherheitsmodell

*Deutsch (Standardsprache) · [English version](Security.en.md) · [Zurück zur Übersicht](../README.md)*

Dieses Dokument beschreibt die Schutzmaßnahmen des Bundles auf fachlicher Ebene.
Es nennt bewusst keine internen Bezeichner, Dateinamen, Prüfreihenfolgen oder
Verfahrensdetails.

## Geltungsbereich

Abgedeckt sind die Bestandteile dieses Bundles: die Ausgabe strukturierter Daten
im Frontend, die Lizenzverwaltung im Backend, der Austausch mit dem Lizenzdienst
und die lokale Ablage des Lizenzzustands.

Nicht abgedeckt sind die Sicherheit der Contao-Installation, des Webservers, der
Datenbank und des Betriebssystems.

## Zugriffskontrolle

- Der Lizenzabschnitt liegt in **System → Einstellungen** und setzt Zugriff auf
  dieses Contao-Modul voraus, das standardmäßig Administratoren vorbehalten ist.
- Jede lizenzverändernde Aktion prüft serverseitig zuerst die
  Modulberechtigung und anschließend das Contao-Anfrage-Token. Erst danach wird
  der gespeicherte Schlüssel gelesen oder eine Verbindung aufgebaut.
- Die Konfigurationsfelder auf Seiten und Datensätzen behandelt Contao als
  geschützte Felder. Redakteurinnen und Redakteure ohne Administratorrechte
  benötigen sie ausdrücklich in ihrer Benutzergruppe.
- Der Lizenzabschnitt kommt ohne Browser-Code aus. Es gibt keine
  JavaScript-Schnittstelle, über die sich Lizenzvorgänge auslösen ließen, und
  keine Verbindung vom Browser zum Lizenzdienst.

## Durchsetzung der Berechtigung

- Die Entscheidung fällt ausschließlich serverseitig.
- Sie wird an mehreren voneinander unabhängigen Stellen ausgewertet, statt an
  einer zentralen Stelle freigeschaltet zu werden. Auch ein direkter Aufruf der
  internen Dienste umgeht die Prüfung nicht.
- Im Frontend wird zusätzlich geprüft, ob der tatsächlich aufgerufene Hostname
  lizenziert ist. Eine Installation, die unter mehreren Namen erreichbar ist,
  liefert nur unter den lizenzierten Namen strukturierte Daten aus.
- Fällt die Prüfung negativ aus, verhält sich Contao exakt wie ohne dieses
  Bundle.

## Schutz des Lizenzzustands

- Der Lizenzzustand liegt unterhalb von `var/` und damit außerhalb des
  öffentlichen Verzeichnisses.
- Verzeichnis und Dateien werden mit eingeschränkten Rechten angelegt
  (`0700` beziehungsweise `0600`).
- Kein Pfad wird aus Anfragedaten abgeleitet.
- Nachträgliche Änderungen am gespeicherten Zustand werden bei der nächsten
  Auswertung erkannt. Die Installation fällt dann in den unlizenzierten Zustand
  zurück; sie wird nicht beschädigt und verliert keine Konfiguration.

## Echtheit und Unverändertheit

- Vom Lizenzdienst empfangene Daten werden vollständig geprüft, bevor sie
  übernommen werden. Eine unvollständig geprüfte Antwort wird nie gespeichert.
- Ein nachträglich verändertes Lizenzdokument wird auch dann abgewiesen, wenn
  begleitende Prüfwerte passend neu berechnet wurden.
- Eine ältere Lizenz kann eine neuere nicht ersetzen.
- Die zur Prüfung nötigen öffentlichen Angaben sind fest im Paket hinterlegt und
  werden zur Laufzeit gegen einen mitgelieferten Prüfwert abgeglichen. Ein
  ausgetauschtes Paket fällt dabei auf.
- Das Paket enthält keinerlei Signaturschlüssel und keine wiederverwendbaren
  Zugangsdaten. Es gibt keine unterstützte Möglichkeit, eigenes Prüfmaterial
  einzubringen.

## Kommunikation nach außen

- Ziel ist ausschließlich der Lizenzdienst von V&T Innovations unter
  `www.v-t.one`. Die Adressen sind fest im Code hinterlegt und lassen sich weder
  durch Konfiguration noch durch Anfragedaten oder Antworten umlenken.
- Verbindungen laufen über HTTPS mit Prüfung von Zertifikat und Hostname.
- Weiterleitungen werden nicht befolgt.
- Zeitlimits und maximale Antwortgrößen sind begrenzt; der Antworttyp wird vor
  der Auswertung geprüft.
- Nutzungs- und Sitzungsmeldungen laufen erst nach dem Ausliefern der Antwort
  und bleiben ohne Auswirkung auf Darstellung und Lizenzgültigkeit.

## Öffentlicher Endpunkt

Für vom Lizenzdienst ausgelöste Aktualisierungen stellt das Bundle einen
öffentlich erreichbaren Endpunkt bereit. Er liegt außerhalb der
Backend-Anmeldung, weil die Gegenstelle ein Server und keine angemeldete Person
ist.

- Angenommen werden ausschließlich kryptografisch authentifizierte Anfragen.
- Angaben zur Herkunft wie `Origin`, `Referer`, User-Agent oder Absender-IP
  gelten nicht als Nachweis.
- Wiederholt eingespielte oder veraltete Anfragen werden abgewiesen. Eine
  unveränderte Wiederholung derselben Anfrage wird als bereits verarbeitet
  gemeldet und nicht erneut angewendet.
- Anfragen werden in Größe, Methode und Inhaltstyp begrenzt, bevor sie
  ausgewertet werden.
- Abgelehnte Anfragen erhalten eine allgemeine Antwort ohne Prüfdetails.
- Der Endpunkt schreibt keine Programmdateien und legt keine Pfade aus
  Anfragedaten an.

## Verhalten bei Fehlern

Zugesichertes Verhalten, das im Code durchgesetzt wird:

- Ein nicht erreichbarer Lizenzdienst, eine Zeitüberschreitung oder eine
  fehlerhafte Antwort verändern eine funktionierende Lizenz nicht.
- Fehlende oder nicht prüfbare Voraussetzungen führen zum unlizenzierten
  Zustand, nicht zu einer Freigabe.
- Änderungen am Lizenzzustand laufen unter einer Sperre und werden erst wirksam,
  nachdem das Ergebnis erneut eingelesen und geprüft wurde. Schlägt diese
  Nachprüfung fehl, wird der vorherige Zustand zurückgeschrieben.
- Fehler einzelner Knotenlieferanten werden abgefangen; eine Seite wird dadurch
  nie unvollständig ausgeliefert.
- Das Bundle löscht, verschiebt und überschreibt keine Inhalte, Dateien oder
  Einstellungen der Installation.

Umgebungsabhängiges Verhalten:

- Die Wirksamkeit von Sperre und Wiederholungsschutz setzt voraus, dass mehrere
  Anwendungsserver auf dasselbe `var/`-Verzeichnis zugreifen.
- Die zuverlässige Ermittlung des Hostnamens setzt korrekt konfigurierte
  `trusted_proxies` und `trusted_hosts` voraus.
- Das Zurückschreiben des vorherigen Zustands setzt voraus, dass das Dateisystem
  zum Zeitpunkt des Fehlers noch beschreibbar ist.
- Die tägliche Aktualisierung setzt einen laufenden Contao-Cron voraus.

## Protokollierung

- Protokolliert werden Vorgang, technische Anfragekennung, HTTP-Status, Dauer,
  Ergebniskategorie und die übernommene Lizenzversion.
- Nicht protokolliert werden Lizenzschlüssel, Prüfsummen, Signaturen,
  übertragene Daten, Antwortinhalte sowie daraus abgeleitete Längen oder
  Prüfwerte.
- Der Lizenzschlüssel erscheint weder in der Browserausgabe noch in
  Fehlermeldungen, Diagnosen oder Sitzungsdaten.
- Meldungen an Administratorinnen und Administratoren sind allgemein gehalten
  und nennen keine Prüfdetails.

## Bekannte Grenzen

- Ausgelieferter Quellcode lässt sich lesen und verändern. Die beschriebenen
  Maßnahmen erschweren Manipulation und machen sie erkennbar; sie sind keine
  Garantie und kein Nachweis der Unumgehbarkeit.
- Wer auf dem Server Schreibrechte am Programmcode besitzt, kann dessen
  Verhalten ändern. Der Schutz des Dateisystems liegt beim Betrieb.
- Ohne gemeinsam genutzten Speicher wirken Sperre und Wiederholungsschutz nur je
  Anwendungsserver.
- Eine stark abweichende Systemzeit kann Lizenzvorgänge verhindern.
- Nutzungsmeldungen erlauben dem Lizenzdienst Rückschlüsse darauf, wann eine
  lizenzierte Installation Seiten ausliefert.

## Verantwortung des Betriebs

- `var/` vor Zugriff über den Webserver schützen und in Sicherungen einschließen.
- Schreibrechte am Programmverzeichnis auf das nötige Maß begrenzen.
- HTTPS mit gültigem Zertifikat betreiben.
- `trusted_proxies` und `trusted_hosts` korrekt setzen.
- Contao, PHP und die Serverumgebung aktuell halten.

## Sicherheitsprobleme melden

Sicherheitsrelevante Beobachtungen bitte über die auf `https://www.v-t.one`
genannten Kontaktwege melden und nicht öffentlich veröffentlichen.

## Weiterführende Dokumente

- [Zurück zur Übersicht](../README.md)
- [Lizenzierung](Lizenzierung.md)
- [English version](Security.en.md)
