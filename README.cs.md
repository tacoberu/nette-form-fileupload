Nette form FileControl
======================

Nahrávání souborů je snadné. Pokud ale máme soubor již nahraný v systému, můžeme chtít

- jej pouze zobrazit (nejlépe s náhledem),
- nebo soubor smazat,
- nebo soubor nahradit jinou verzí.

Poněkud nepohodlná je situace, kdy nám vznikne nějaká nesouvisející chyba jinde ve formuláři.
Formulář tedy vrátíme, ať si to uživatel opraví. Ale nahrávaný soubor, nebo soubory musí navolit znova.
Toto řešíme pomocí transakce. Jednou nahraný soubor je uschován ve specielním úložišti (v defaultu řešena jako adresář do tempu, možné změnit),
a po úspěšném uložení formuláře zanesen do systému.

Obrázkové soubory reprezentujeme jako obrázky. Pokud standardní renderer nevyhovuje, můžeme nastavit vlastní.

Hodnota inputu může nabývat tří možností:

- `Null`: žádný, nebo původní soubor smazán
- `FileUploaded`: nahraný nový soubor
- `FileCurrent`: původní soubor uložený v systému


## Instalace
```
composer require tacoberu/nette-form-fileupload
```


### Použití

```php
use Taco\Nette\Forms\Controls\FileCurrent;
use Taco\Nette\Forms\Controls\FileControl;
use Taco\Nette\Forms\Controls\GenericFilePreviewer;

$form = new Nette\Forms\Form;

$form['portrait'] = (new FileControl('Portrait:'))
	->setDefaultValue(new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg"))
	->setPreviewer(new GenericFilePreviewer());


```
