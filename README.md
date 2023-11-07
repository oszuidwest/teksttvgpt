# Tekst TV GPT WordPress Plugin

De Tekst TV GPT-plugin voor WordPress gebruikt OpenAI's GPT-modellen om automatisch korte versies van artikelen te maken. Deze kunnen gebruikt worden voor een tekst tv-uitzending ('kabelkrant') of teletekst.

![aigen](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken
- **AJAX-aangedreven samenvattingsgeneratie:** Genereert samenvattingen zonder de pagina te herladen voor een naadloze gebruikerservaring.
- **Integratie met OpenAI API:** Maakt gebruik van OpenAI's geavanceerde verwerking van natuurlijke taal om samenvattingen te creëren die zowel nauwkeurig als samenhangend zijn.
- **Foutafhandeling:** Handelt fouten netjes af, zoals onvoldoende woordenaantal of ontbrekende API-sleutels.

## Installatie
1. Upload de pluginbestanden naar de `/wp-content/plugins/` directory.
2. Activeer de plugin via het 'Plugins' scherm in WordPress.
3. Gebruik de plugin via de editor waar de `generateSummary` knop beschikbaar is.

## Gebruik
- Zorg ervoor dat de OpenAI API-sleutel correct is geconfigureerd in de plugininstellingen.
- In de WordPress-editor ziet u een "Genereer Samenvatting" knop. Door op deze knop te klikken, wordt het proces van samenvattingsgeneratie geactiveerd.
- De gegenereerde samenvatting wordt weergegeven in een speciaal tekstveld.

## Vereisten
- Gebruik van het streekomroep WordPress-thema *
- Classic Editor geactiveerd in WordPress
- Een geldige OpenAI API-sleutel.
- WordPress 6.0 of hoger.
- PHP 8.1 of hoger.

__* Deze plug-in is gemaakt als 'first party addon' voor het streekomroep WordPress-thema van ZuidWest TV. Dit thema is gratis te downloaden. Met kleine aanpassingen zou de plugin met ieder thema kunnen werken.__

## Licentie
Deze plugin is gelicenseerd onder de [LICENTIE](LICENSE) die in de repository is opgenomen.
