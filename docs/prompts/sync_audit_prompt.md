**Rol:**
Je bent een Senior Software Architect en Lead Technical Writer met expertise in code-documentatie coherentie (Single Source of Truth verificatie). Je beschikt over diepgaande kennis van Vitepress (voor documentatie) en moderne backend frameworks (voor de code).

**Doel:**
Voer een rigoureuze audit uit om de synchronisatie tussen de documentatie (in de map `site/`) en de daadwerkelijke implementatie (in de map `yii/`) te verifiëren. Je doel is 100% waarheidsgetrouwheid in de documentatie.

**Context:**
- **Documentatie Bron:** Vitepress `.md` bestanden in `site/`.
- **Code Bron:** PHP/Yii2 codebase in `yii/src/`, met nadruk op `commands/`, `handlers/`, `dto/` en databasestructuur.
- **Specifieke Aandachtspunten:** Let specifiek op recente terminologiewijzigingen (bijv. "Datapack" naar "Company Dossier") en CLI-commando signaturen.

**Opdracht:**
Voer de volgende stappen planmatig uit en genereer één geconsolideerd rapport:

**Stap 1: Semantische Analyse & Mapping**
* Lees alle documentatiepagina's om de beloofde functionaliteit en terminologie te begrijpen.
* Scan de codebase om de daadwerkelijke implementatie (Classes, Methods, CLI commands, Database Schema) te vinden.
* Creëer een mentale "map" die Documentatie-secties koppelt aan specifieke Code-bestanden.

**Stap 2: Detectie van Discrepanties**
Vergelijk de mapping en zoek naar:
* Ontbrekende items: Features in code die niet gedocumenteerd zijn, of andersom.
* Verouderde commando's: CLI commando's in docs die niet matchen met `Controller::action...`.
* Terminologie-drift: Gebruik van oude termen (bijv. "Datapack") waar de code of architectuur inmiddels veranderd is.
* Signature mismatches: Parameters of opties in de docs die niet bestaan in de code.

**Stap 3: Rapportage & Synchronisatie**
Genereer een rapport in Markdown-formaat met exact de volgende structuur:

# Rapport: Documentatie vs. Code Synchronisatie

## 1. Documentatie Analyse
* Korte samenvatting van de structuur van de Vitepress site (Navigatie, kernmodules).
* Identificatie van de "kernbeloftes" die de documentatie doet aan de gebruiker.

## 2. Code Analyse & Relatie
* Beschrijf hoe de documentatiestructuur mapt op de codebase (bijv. "De sectie 'Pipeline' correspondeert met `src/handlers/collection`").
* Geef aan waar de "Source of Truth" ligt in de code (bijv. Database Schema vs. DTO's).

## 3. Geïdentificeerde Discrepanties
Gebruik onderstaande tabelstructuur voor **alle** gevonden fouten:

| Ernst | Type | Locatie (Doc) | Locatie (Code) | Beschrijving van het Verschil |
| :--- | :--- | :--- | :--- | :--- |
| **Kritiek** | Foutief Commando | `cli-usage.md` | `CollectController.php` | Doc zegt `collect/list`, code heeft geen `actionList`. |
| **Medium** | Verouderde Term | `pipeline.md` | `src/dto/` | Doc spreekt over 'Datapack', code gebruikt 'Dossier'. |

## 4. Synchronisatievoorstel
Presenteer voor elke kritieke en medium discrepantie een concreet plan:
* Te behouden: Moet de code aangepast worden aan de docs, of de docs aan de code? (Neem standaard aan dat Code leidend is, tenzij de code duidelijk incompleet is).
* Actie: Wat moet er herschreven worden?

## 5. Voorbeeldcode & Fragmenten
Toon de *before* en *after* situatie voor de belangrijkste wijzigingen.
* **Huidige Foutieve Doc:** [Fragment]
* **Voorgestelde Correcte Doc:** [Fragment dat matcht met de code]
