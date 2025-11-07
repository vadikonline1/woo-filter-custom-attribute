# FAC – Filter Custom Attribute

**Contributors:** steel_xd  
**Tags:** woocommerce, filter, custom attributes, shortcode, widget  
**Requires at least:** 6.0  
**Tested up to:** 6.5  
**Requires PHP:** 7.4  
**Stable tag:** 1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

FAC – Filter Custom Attribute permite filtrarea produselor WooCommerce după atribute/taxonomii personalizate prin widget-uri și shortcode-uri.

---

## Description

FAC este un plugin WordPress pentru WooCommerce care adaugă filtre inteligente pentru produsele tale. Poți să creezi filtre pentru atribute personalizate sau taxonomii și să le afișezi prin widget-uri sau shortcode-uri.

**Caracteristici principale:**
- Filtrare produse WooCommerce după atribute personalizate.
- Widget pentru sidebar sau zone widget.
- Shortcode pentru plasare oriunde pe site.
- Filtre multiple (AND/OR) cu validare termeni existenți.
- Integrare cu filtrele active WooCommerce.
- Compatibil cu WordPress 6.x și WooCommerce 7.x+.

---

## Installation

1. Descarcă pluginul și dezarhivează-l.  
2. Copiază folderul `fac-filter-custom-attribute` în `wp-content/plugins/`.  
3. Activează pluginul din meniul WordPress Admin → Plugins.  
4. Configurează pluginul în:
   - **Appearance → Widgets** pentru a adăuga widget-ul FAC Filter.
   - Sau folosește shortcode-ul `[fac_filter]` în pagini, postări sau template-uri.

---

## Usage

### Widget

1. Mergi la **Appearance → Widgets**.  
2. Trage widget-ul **FAC Filter Widget** în zona dorită.  
3. Selectează taxonomia/atributul dorit și salvează.

### Shortcode

- `[fac_filter]` – afișează toate filtrele disponibile.  
- `[fac_filter taxonomy="pa_color"]` – afișează filtre pentru atributul `pa_color`.  

### Active Filters Integration

Pluginul adaugă automat filtrele selectate la lista de filtre active WooCommerce, cu posibilitatea de a le elimina direct din interfață.

---

## Demo Examples

### 1. Utilizarea Shortcode-ului
- `[fac_filter]` – afișează toate filtrele disponibile.  
- `[fac_filter taxonomy="pa_color"]` – afișează doar filtrele pentru atributul „Culoare”.  
- `[fac_filter taxonomy="pa_size"]` – afișează filtre pentru atributul „Mărime”.

**Exemplu URL:**  `https://exemplu-site.ro/shop/?filter_pa_color=red,blue&filter_pa_size=medium`

Filtrele vor apărea și în lista de **Active Filters** WooCommerce.

### 2. Configurarea Widget-ului FAC Filter

1. Mergi la **Appearance → Widgets**.  
2. Trage widget-ul **FAC Filter Widget** în zona dorită.  
3. Selectează taxonomiile/atributele pe care vrei să le afișezi, ex: `pa_color` (Culoare), `pa_size` (Mărime).  
4. Salvează și vizualizează filtrul pe front-end.

**Exemplu URL:**  `https://exemplu-site.ro/product-category/shirts/?filter_pa_color=green&filter_pa_size=large`


### 3. Active Filters (Filtre active)

- Fiecare filtru aplicat apare în lista **Active Filters** de WooCommerce, cu link de eliminare.

**Exemplu:**  
URL: `https://exemplu-site.ro/shop/?filter_pa_color=red&filter_pa_size=medium`  
Active Filters afișate:  
- Culoare: Red → [Remove]  
- Mărime: Medium → [Remove]  

### 4. Exemple de multiple filtre

**URL:** `https://exemplu-site.ro/shop/?filter_pa_color=red,blue&filter_pa_size=small,medium`

Logica combină filtrele în relație **AND** între taxonomii și **IN** pentru termeni multipli din aceeași taxonomie.

---

## Frequently Asked Questions

**Este compatibil cu toate temele WooCommerce?**  
Da, pluginul folosește standarde WooCommerce și ar trebui să funcționeze cu majoritatea temelor. Testează întotdeauna pe site live.

**Pot folosi mai multe filtre în același timp?**  
Da, pluginul suportă filtre multiple aplicate simultan și le combină logic cu relația AND.

**Funcționează cu filtre AJAX?**  
Nu implicit, dar se poate extinde ușor pentru filtrare live AJAX.

---

## Screenshots

1. Widget-ul în zona Sidebar.  
2. Exemple de filtre aplicate.  
3. Shortcode afișat pe pagină.  
4. Lista de filtre active WooCommerce.

---

## Changelog

**1.0**  
- Versiune inițială.

---

## Upgrade Notice

**1.0**  
- Prima versiune publicată.

---

## License

GPLv2 or later  
[GPL License](https://www.gnu.org/licenses/gpl-2.0.html)
