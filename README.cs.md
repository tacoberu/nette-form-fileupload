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


### Transakce

Když je soubor úspěšně nahrán na server, je automaticky přesunut do úložiště, transakce. To poslouží k tomu, že
pokud není formulář zpracován, ale je například z důvodu validace předán zpět uživateli, není nutné soubor nahrávat znova.
Po úspěšném zpracování je soubor k dispozici pomocí $control->getValue() jako ostatní hodnoty.

Poté, co je soubor nahrán do systému je možné transakci zahodit. Tedy, v případě
použití defaultního úložiště `UploadStoreTemp`, smazat adresář. To můžeme buď udělat explicitně:

```php
$form['portrait']->destroyStore();
```

Nebo to nechat na GC, který jej za určitou dobu smaže automaticky.

#### UploadStoreTemp, GC

Automatické promazávání je implementováno v `UploadStoreTemp`. Chová se to tak,
že po ukončení stránky se díky destruktoru projdou všechny patřičné transakce
a zkontroluje se, zda je transakce starší jak `UploadStoreTemp::$gcAgeLimit`.
Aby se rozložila zátěž, smaže se tak pouze `UploadStoreTemp::$gcMaxCount` transakcí/adresářů.

Toto chování je záležitostí pouze implementace `UploadStoreTemp`.
