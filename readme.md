# vtinnovations/schema-org

Automatische **Schema.org-Daten (JSON-LD)** für Contao 5 — für klassisches SEO
*und* KI-Antwortmaschinen (Google AI Overviews, ChatGPT, Perplexity, Gemini).
Ein zusammenhängender `@graph`, per `@id` verknüpft, auf jeder Frontend-Seite
eingebunden.

## Was ausgegeben wird

Das Bundle baut einen einzelnen `<script type="application/ld+json">`-Block mit
einem quer­verlinkten `@graph`:

| Knoten | Quelle | Details |
|--------|--------|---------|
| `Organization` / `LocalBusiness` | Root-Seite | Logo, sameAs, Adresse, Geo, Öffnungszeiten, ContactPoint |
| `WebSite` + `SearchAction` | Root-Seite | Sitelinks-Suchbox / maschinenlesbarer Sucheinstieg |
| `WebPage` (oder Subtyp) | jede Seite | `dateModified`-Frische, `isPartOf`, `about`, `speakable` |
| `BreadcrumbList` | Seiten-Trail | vom WebPage-Knoten referenziert |
| `Article` / `NewsArticle` / `BlogPosting` | `tl_news`-Detail | Autor `Person`, Bild, Publisher, `mainEntityOfPage` |
| `Event` | `tl_calendar_events`-Detail | Start/Ende, Status, Teilnahmeart, Ort, Organisator |
| `FAQPage` → `Question`/`Answer` | `tl_faq` auf der Seite | stärkster Hebel für Zitate in AI Overviews |
| *beliebig* | eigenes JSON-LD-Feld | Product, HowTo, Recipe, Review … pro Seite/Datensatz |

News-, Kalender- und FAQ-Knoten werden nur eingebunden, wenn das jeweilige
Contao-Bundle installiert ist.

## Warum Einbindung über den Response-Body (nicht TL_HEAD)

Das JSON-LD wird per `kernel.response` direkt vor `</head>` geschrieben. Eigene
`fe_page`-Templates (Page-Builder, Award-Themes) lassen den `TL_HEAD`-Insert-Tag
oft weg, wodurch Head-Markup still verschluckt würde. Das Umschreiben der
Response ist die einzige Stelle, die jedes Template überlebt.

## Konfiguration

Alles wird im Seitenbaum gepflegt — kein separater Einstellungs-Screen:

- **Root-Seite → „Schema.org / Strukturierte Daten"**: site­weite Organisation,
  Adresse, Social-Profile, Suchbox.
- **Jede andere Seite → „Schema.org"**: deaktivieren, WebPage-Subtyp,
  Speakable-Selektoren, eigenes JSON-LD.
- **News-/Event-/FAQ-Datensatz → „Schema.org"**: Typ-Override, Autor,
  Event-Status, Einzel-Deaktivierung, eigenes JSON-LD.

**Backend → Schema.org** zeigt das generierte JSON-LD einer Seite und verlinkt
zum Google-Rich-Results-Test und zum schema.org-Validator.

## Erweitern

Eigenen Knoten per getaggtem Service ergänzen:

```php
final class MyProvider implements VTinnovations\SchemaOrg\Schema\NodeProviderInterface
{
    public function getPriority(): int { return 40; }
    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $graph->add(['@type' => 'Product', '@id' => $ctx->pageUrl.'#product', /* … */]);
    }
}
```

Die Autokonfiguration taggt den Service über `NodeProviderInterface`; höhere
Priorität läuft zuerst, sodass Basis-Knoten existieren, bevor abhängige Knoten
darauf verweisen.

## Lizenzierung

Kostenpflichtiges Produkt, geprüft gegen den V&T-Lizenzserver (v-t.one,
Produkt-Code `vt-schema-org`) — dasselbe Modell und derselbe Server wie beim
Migrator-Bundle.

- **Sperre:** Ohne gültige Lizenz gibt das Frontend **kein** JSON-LD aus. Den
  Schlüssel unter **Backend → Schema.org** eingeben; er wird einmal geprüft und
  in `var/schema-org/license.json` zwischengespeichert.
- **Kulanz:** Der Cache wird 7 Tage lang vertraut; ein täglicher Cron prüft
  erneut, sodass ein widerrufener/abgelaufener Schlüssel das Plugin ohne
  manuelles Zutun sperrt. Ein vorübergehender Serverausfall hält das
  Kulanzfenster, eine explizite Ablehnung sperrt sofort.
- **Lokaler Bypass:** Umgebungsvariable `SCHEMA_ORG_LICENSE_BYPASS=1` schaltet
  das Plugin auf einer Dev-/Staging-Box ohne Schlüssel frei. Niemals in
  Produktion setzen.

## Hinweis zu vtinnovations/seo-studio

SEO Studio liefert ein leichtgewichtiges Schema-Feature (lose
Organization-/Breadcrumb-/Article-Blöcke). Dieses Bundle löst es mit einem
einzelnen zusammenhängenden `@graph` und deutlich breiterer Typabdeckung ab.
Sind beide aktiv, sollte das *Schema*-Feature von SEO Studio deaktiviert werden,
um doppeltes Markup zu vermeiden.

---

© V&T Innovations 2026 — LGPL-3.0-or-later
