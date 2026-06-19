Nette form FileControl
======================

**FileControl** a **MultiFileControl** jsou Nette form inputy pro nahrávání souborů, které se **chovají stejně jako ostatní Nette inputy**: `setValue()` nastavuje hodnotu, `getValue()` ji vrací, validace, podmíněná validace i error messages fungují identicky jako u textových či select inputů.

Standardní `<input type="file">` má při editaci existujících dat několik nepříjemností:

1. Nelze rozumně zobrazit původní soubor — na rozdíl od ostatních inputů, kde lze původní hodnotu jednoduše zobrazit.
2. S tím souvisí i to, jak existující soubor smazat.
3. Při nesouvisející chybě jinde ve formuláři musí uživatel nahrávat soubor znovu.
4. Nahrávání velkých souborů je kapitola sama o sobě.

FileControl toto řeší:

1. Existující soubor je reprezentován hodnotou třídy `FileCurrent`. Pokud jej uživatel smaže, formulář to ví.
2. Nahrávané soubory se ukládají v transakci — jednou nahraný soubor není potřeba nahrávat znovu, i když formulář selže z jiného důvodu.

Hodnota inputu může být:

- `null` — žádný soubor, nebo původní soubor smazán
- `FileUploaded` — nově nahraný soubor (uložený v transakci, čeká na commit do systému)
- `FileCurrent` — původní soubor uložený v systému


## Instalace

```
composer require tacoberu/nette-form-fileupload 
```


## Verze a požadavky

| Branch | PHP | Nette |
|--------|-----|-------|
| `v2.0` | >= 8.1 | ^3.2 |
| `v1.2` | >= 7.4 | ^3.1 |



## Rychlý start

Do `config.neon` zaregistrujte rozšíření:

```neon
extensions:
    filecontrol: Taco\Nette\Forms\Controls\FileControlExtension

filecontrol:
    store: Taco\Nette\Forms\Controls\UploadStoreTemp('uploading/txt-', null, %tempDir%)
```

Ve formuláři:

```php
$form->addFileControl('portrait', 'Portrait');
$form->addMultiFileControl('attachments', 'Přílohy');
```

Formulář s jedním souborem (`FileControl`) — u nahraného souboru je tlačítko pro smazání:

![Formulář s FileControl](docs/file.png)

Formulář s více soubory (`MultiFileControl`) — s náhledy obrázků, mazáním položek (✕) a tlačítkem ↻ pro přednahrání:

![Formulář s MultiFileControl](docs/files.png)

![Typický formulář s avatarem](docs/bio.png)

Spustitelné ukázky jsou v adresáři [`examples/`](examples/).


## Funkce

### Funguje bez JS

**Controly jsou plně funkční i bez JavaScriptu.** Tlačítko ↻ umožňuje nahrát soubory před odesláním formuláře — stránka provede round-trip, ale stav formuláře se zachová. Validace, chyby i transakce fungují stejně.

### JS vylepšení: AJAX nahrávání a doplňky k tlačítku ↻

I bez JS jsou controly plně funkční (viz výše) — tlačítko ↻ je vidět a uživatel na něj kliká sám. `assets/filecontrol.ts` / `assets/filecontrol.js` nad tímto základem nabízí volitelná JS vylepšení, která lze zakomponovat do libovolného frontendu:

- `initMultiFileAjaxUpload(container)` — nahradí round-trip okamžitým AJAX nahráním pro `MultiFileControl`. Spustí se podle atributu `data-upload-url` na containeru — ten si knihovna nastaví sama, je-li control vykreslen v rámci Presenteru, takže o něj není potřeba se starat.
- `initFileAjaxUpload(container)` — totéž pro `FileControl`.
- `initMultiFileAutoPreload(container)` — pro případy, kdy AJAX URL k dispozici není: skryje tlačítko ↻ a po výběru souborů ho za uživatele samo "klikne", takže round-trip proběhne automaticky místo ručního kliknutí.
- `initFileHideOnNew(container)` — obdoba pro `FileControl`: po výběru nového souboru skryje tlačítko pro smazání a popisek původního souboru, aby nepřekážely.
- `initFileClearButton(fileInput)` — přidá za `<input type="file">` tlačítko ✕ pro vyčištění vybraných souborů.

Vlastnosti AJAX nahrávání (`initMultiFileAjaxUpload` / `initFileAjaxUpload`):

- **Okamžité nahrání po výběru** — není potřeba klikat ↻ ani odesílat formulář
- **Chunked přenos pro velké soubory** — soubory se automaticky rozdělí, aby každý POST byl pod `upload_max_filesize`; server je v transakci složí zpět
- **Progress bar** — `<progress>` element během chunked přenosu
- **Inline náhled** — server vrátí náhled nebo jmenovku souboru, vloží se bez přenačtení stránky

```js
import {
    initMultiFileAjaxUpload, initMultiFileAutoPreload,
    initFileAjaxUpload, initFileHideOnNew, initFileClearButton,
} from './filecontrol.js';

// data-upload-url si nastavuje knihovna sama, je-li control vykreslen v Presenteru —
// tady se podle něj jen rozhoduje, zda zapojit AJAX, nebo JS doplněk k ručnímu ↻.
document.querySelectorAll('[data-taco-type="file multiple"]').forEach(el => {
    el.dataset.uploadUrl ? initMultiFileAjaxUpload(el) : initMultiFileAutoPreload(el);
});
document.querySelectorAll('.taco-filecontrol-single').forEach(el => {
    el.dataset.uploadUrl ? initFileAjaxUpload(el) : initFileHideOnNew(el);
});
document.querySelectorAll('.taco-filecontrol-single input[type="file"]')
    .forEach(initFileClearButton);
```

### Validace

Funguje stejně jako u jiných Nette inputů — plně kompatibilní s `addConditionOn()`, `addRule()` i chybovými hláškami:

```php
$form->addFileControl('portrait', 'Portrait')
    ->setRequired('Vyberte prosím soubor.')
    ->addRule($form::MaxFileSize, 'Soubor je příliš velký (max %d B).', 512 * 1024)
    ->addRule($form::MimeType, 'Povoleny jsou jen obrázky.', ['image/jpeg', 'image/png'])
    ->addRule($form::Image, 'Soubor musí být obrázek.');
```

| Pravidlo | Popis |
|---|---|
| `Form::Required` / `setRequired()` | Soubor musí být vybrán nebo již existovat jako `FileCurrent`. |
| `Form::MaxFileSize` | Maximální velikost souboru v bajtech. |
| `Form::MimeType` | Povolené MIME typy, např. `'image/jpeg'` nebo pole typů. |
| `Form::Image` | Zkratka pro podporované formáty (`image/jpeg`, `image/png`, `image/gif`, `image/webp`). |

### Chyby nahrávání

- **Soubor překročí `upload_max_filesize`** — PHP označí soubor chybou `UPLOAD_ERR_INI_SIZE`, control zobrazí zprávu s hodnotou limitu.
- **Součet souborů překročí `post_max_size`** — PHP tiše vyprázdní celý POST. Control to detekuje z `Content-Length` a přidá chybovou zprávu na úrovni formuláře.

---

## API

### setPreviewer()

Nastavení previeweru pro formátování náhledů. `GenericFilePreviewer` zobrazuje náhledy obrázků.

### getRemoveButtonPrototype()

Přizpůsobení mazacího tlačítka: label, třídy, title.

### Transakce

Nahraný soubor je automaticky přesunut do úložiště (transakce). Díky tomu není nutné soubor nahrávat znovu při validační chybě. Po úspěšném zpracování je dostupný přes `getValue()` jako ostatní hodnoty.

Po uložení do systému lze transakci zahodit explicitně:

```php
$form['portrait']->destroyStore();
```

Nebo ji nechat na GC, který ji smaže automaticky po uplynutí `UploadStoreTemp::$gcAgeLimit`.


## Sestavení assets (TypeScript)

```bash
npm run build:assets
```

Zkompilovaný soubor: `assets/filecontrol.js` (symlink v `examples/document_root/js/`).


## E2E testy (Playwright)

```bash
npm install
npx playwright install chromium  # jen poprvé

npm run test:e2e        # spustit testy
npm run test:e2e:ui     # interaktivní UI
```

Adresa testované aplikace se nastavuje v `.env` přes `APP_URL`. Výstupy se ukládají do `temp/`.
