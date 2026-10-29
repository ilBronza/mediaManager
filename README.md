# IlBronza Media Manager

Package Laravel per navigare directory reali, conservare metadati e collegamenti ai model e ritrovare file spostati all'esterno del gestionale.

Il prototipo attuale implementa **dischi locali Laravel**, anche più di uno: ogni `Entry` rappresenta un file o una directory fisica. Il percorso è relativo alla root del disco; `parent_id` riproduce l'albero. L'UUID rimane stabile durante rinomina, spostamento e recupero. I record mancanti conservano l'ultima posizione conosciuta e le relazioni. La scelta dello storage effettivo resta all'applicazione: il package non sceglie o formatta filesystem come ext4/APFS/NTFS.

**File Cabinet comanda all'inserimento:** per ogni nuovo media determina disk, cartella, nome e collection con le proprie regole. Media Manager registra il risultato dopo la scrittura. Uno spostamento successivo modifica la posizione di quel media, senza cambiare il template, la riga o la destinazione dei caricamenti futuri.

## Più archivi e responsabilità

I disk si definiscono una sola volta nell'applicazione, in `config/filesystems.php`. Il loro nome identifica driver e root; non è un percorso fisico. Esempio:

```php
'disks' => [
    'documenti' => ['driver' => 'local', 'root' => storage_path('app/documenti'), 'throw' => true],
    'immagini' => ['driver' => 'local', 'root' => storage_path('app/immagini'), 'throw' => true],
],
```

Media Manager abilita soltanto quali disk rendere gestibili:

```php
// config/media-manager.php
'disks' => ['documenti', 'immagini'],
```

File Cabinet continua a scegliere uno di questi nomi tramite la configurazione della propria riga. Può anche utilizzare un disk non abilitato nel Media Manager: il caricamento mantiene il comportamento esistente e non viene indicizzato automaticamente. Ogni posizione è identificata dalla coppia `(disk, percorso relativo)`: lo stesso `dossier/file.pdf` su due disk rappresenta due file distinti.

Il prototipo consente navigazione e gestione indipendente di più disk locali. **Trasferire un media da un disk a un altro non è ancora implementato.** S3/SFTP richiedono un backend operativo specifico: avere driver Laravel disponibili non rende portabili automaticamente hard link, inode, lock e xattr. Un NAS montato come directory locale va verificato per queste capacità prima di abilitarlo. I nomi e le credenziali degli storage non vengono duplicati nel package.

## Installazione nell'applicazione

PHP 8.2+, Laravel 10.37+ (suite del package verificata su Laravel 11 e integrazione Padovanio su Laravel 10.50), database e un disco locale dedicato. L'interfaccia usa i package IlBronza CRUD, Datatables, Menu e UikitTemplate dell'applicazione, con i loro provider e asset già configurati. Il package non modifica i package fratelli né migra automaticamente dati esistenti.

Il package è disponibile su [Packagist](https://packagist.org/packages/ilbronza/media-manager), con il codice sorgente su [GitHub](https://github.com/ilBronza/mediaManager). Nell'applicazione:

```sh
composer require ilbronza/media-manager:^0.1
php artisan vendor:publish --tag=media-manager.config
php artisan migrate
```

Per lavorare sul sorgente locale, in alternativa aggiungere al `composer.json` dell'applicazione:

```json
{
    "repositories": [{"type": "path", "url": "../pacchetti/mediaManager"}],
    "require": {"ilbronza/media-manager": "@dev"}
}
```

Adattare il percorso al progetto. Quindi, nell'ambiente di sviluppo dell'applicazione:

```sh
composer update ilbronza/media-manager
php artisan vendor:publish --tag=media-manager.config
php artisan migrate
```

Configurare un disco **privato**, con root esistente, in `config/filesystems.php`:

```php
'archive' => [
    'driver' => 'local',
    'root' => storage_path('app/archive'),
    'throw' => true,
],
```

In `config/media-manager.php`:

```php
'disks' => ['archive'],
```

Nessun disco è abilitato per default. Le root dei dischi abilitati devono essere disgiunte. I link simbolici non sono seguiti; i nomi `.media-manager*` sono riservati. Una root assente non viene creata implicitamente durante la scansione. La sua identità viene controllata prima delle operazioni per rilevare sostituzioni o mount diversi; dopo una migrazione intenzionale dello storage, verificare e aggiornare esplicitamente il binding della root.

## Collegamento al menu del gestionale

Aggiungere l'alias del package all'elenco esistente in `config/menu.php`:

```php
'serviceProviders' => [
    // Altri package già configurati...
    'mediaManager',
],
```

Il metodo `app('mediaManager')->manageMenuButtons()` crea il gruppo **Media Manager**, con **Catalogo** e **Filesystem**. È disponibile anche il nome completo `\IlBronza\MediaManager\MediaManager::class` al posto dell'alias. La dichiarazione delle voci è in `Helpers/Menu/MediaManagerMenuHelper.php`; viene usata l'API standard del package Menu e i link sono generati dalle rotte nominate, anche con un prefisso URL personalizzato.

Le voci sono disponibili agli utenti con almeno uno di questi ruoli, configurati in `config/media-manager.php`:

```php
'defaultRoles' => ['administrator', 'mediaManager'],
```

Il nome del ruolo `mediaManager` distingue maiuscole e minuscole. Il package non crea né assegna ruoli agli utenti: l'assegnazione resta al gestionale. Anche le rotte sono protette dal middleware derivato da `CRUDBasePackageMiddlewareRolesPermissions`, aggiunto a tutte le rotte del package oltre ai middleware configurati. Quindi nascondere una voce non è l'unico controllo: senza uno dei ruoli previsti, gli URL diretti, i download e le azioni sui file sono negati. Il ruolo `superadmin` da solo non sostituisce uno di questi due ruoli.

Serve almeno un disk configurato in `media-manager.disks` e autorizzato dal relativo Gate. Le sottovoci prive di dischi accessibili sono omesse; senza sottovoci o con `routes => false` non viene creato un gruppo vuoto. Non viene modificato automaticamente `config/menu.php` dell'applicazione. Se l'app usa la cache di configurazione, rigenerarla dopo l'aggiunta dell'alias.

## Autorizzazioni e interfaccia

I middleware predefiniti sono `web, auth`, ai quali il package aggiunge sempre il controllo dei ruoli. Le due viste condividono esattamente `resources/views/browser.blade.php`, con navigazione a sinistra e DataTable a destra:

- `/media-manager`: **Catalogo**, con le sole voci presenti in `media_manager_entries`, incluse quelle mancanti. Si consulta anche con storage non disponibile; lo stato mostrato è quello dell'ultima verifica.
- `/media-manager/filesystem`: **Filesystem**, con i file e le directory fisiche della cartella corrente, anche senza importazione iniziale. Il badge **A database** segnala una corrispondenza di disco e percorso nel catalogo, anche senza associazioni a dossier. `Collegato` è una colonna distinta e riguarda le relazioni del record.

La navigazione non calcola SHA-256 dei file, non scrive UUID, non crea Entry e non applica riconciliazioni. Il badge segnala un percorso registrato: non certifica che i byte o l'identità fisica siano rimasti invariati dopo modifiche esterne. Si naviga entro le root dei disk abilitati; symlink e nomi riservati `.media-manager*` sono esclusi. I record storici `superseded` non occupano una posizione attiva.

Il package registra i Gate di base solo se non sono già definiti dall'applicazione: autorizzano i ruoli previsti sui soli disk abilitati. L'applicazione può definire regole più restrittive per singoli file, tenant o dischi. Il Gate `view-dossier` resta di competenza dell'applicazione e non viene concesso automaticamente:

| Gate | Argomenti dopo l'utente | Responsabilità |
|---|---|---|
| `media-manager.browse` | `$disk` | Elencare l'archivio |
| `media-manager.filesystem` | `$disk` | Navigare i percorsi fisici del disco, inclusi quelli non indicizzati |
| `media-manager.view` | `$entry` | Vedere e scaricare una voce |
| `media-manager.manage` | `$disk, $entry` | Scrivere metadati, creare, caricare e spostare |
| `media-manager.reconcile` | `$disk` | Leggere il rapporto completo del disco |
| `media-manager.view-dossier` | `$dossier, $entry` | Mostrare il collegamento al dossier |

Per gli spostamenti vengono autorizzati sorgente, cartella di destinazione e, per una directory, tutti i discendenti indicizzati. Applicare nei Gate anche i vincoli del dossier e dell'eventuale tenant. I servizi PHP e i comandi Artisan sono API amministrative fidate: chi li invoca deve autorizzare l'operazione.

Il Gate `filesystem` concede la lettura dell'elenco fisico dell'intero disco: va assegnato agli utenti che possono vedere anche file non ancora catalogati. Le voci note rimangono soggette a `view`; un URL con un percorso arbitrario non permette di attraversare una directory del catalogo non autorizzata. Il nuovo browser espone navigazione e, nel Catalogo, le singole azioni SHA/UUID. Le API preesistenti per upload, directory, spostamento, metadati e recupero restano disponibili, mentre la loro nuova UI sarà definita separatamente. Il download verifica l'impronta prima di servire il contenuto.

## UIkit, DataTable e campi

Il layout predefinito è `uikittemplate::app`, configurabile con `media-manager.layout`; un layout alternativo deve fornire la sezione `content` e gli asset standard UIkit/Datatables dell'applicazione. Non vengono inclusi una seconda copia di UIkit, un CDN o uno script DataTables alternativo.

`BrowserTableHelper` usa la factory `create()` ereditata da `Datatables`, la stessa utilizzata da `CRUDIndexTrait`. `BrowserDatatable` disabilita soltanto il pulsante di reload AJAX per queste tabelle alimentate da array; **Aggiorna** ricarica anche la navigazione. Ricerca globale, filtri di colonna, ordinamento e paginazione sono quelli del package. Le righe autorizzate dell'intera cartella vengono passate alla DataTable; la paginazione non limita la ricerca ai primi 50 elementi. La lettura è limitata alla cartella aperta, senza scansione ricorsiva. Directory molto grandi comportano un caricamento in memoria dell'intero elenco.

I campi si mantengono in:

- `src/Providers/FieldsGroups/CatalogFieldsGroupParametersFile.php`;
- `src/Providers/FieldsGroups/FilesystemFieldsGroupParametersFile.php`.

L'app ospite può sostituire i provider con le chiavi `media-manager.models.entries.fieldsGroupsFiles.index` e `media-manager.models.filesystem.fieldsGroupsFiles.index`. Ogni colonna dichiara tipo, etichetta, visibilità e ordinamento nel formato standard del CRUD. Le icone operative sono una colonna dichiarata nello stesso array.

## Helper indipendenti e azioni sul singolo file

Gli helper di lettura in `IlBronza\MediaManager\Helpers\Browser` separano catalogo, filesystem e abbinamento dei percorsi:

```php
use IlBronza\MediaManager\Helpers\Browser\{
    CatalogListingHelper,
    FilesystemListingHelper,
    CatalogOverlayHelper
};

$registered = app(CatalogListingHelper::class)->read('archive', 'documento.pdf');
$physical = app(FilesystemListingHelper::class)->read('archive', 'documento.pdf');
$annotated = app(CatalogOverlayHelper::class)->attachFile('archive', $physical);

$folder = app(FilesystemListingHelper::class)->list('archive', 'clienti');
$annotatedFolder = app(CatalogOverlayHelper::class)->attach('archive', $folder);
```

`read()` lavora su una voce, `list()` sui figli immediati. Questi helper non dipendono da HTTP, sessione o Blade; chi li invoca deve autorizzare l'accesso. `BrowserNavigationHelper`, `BrowserRowsHelper` e `BrowserTableHelper` si occupano invece della presentazione.

Nel Catalogo ogni file dispone dell'icona **Informazioni**, che apre nel layout condiviso la scheda con percorso, metadati e collegamenti. `ReadCatalogEntryHelper::run($entry)` legge questi dati dal database senza accedere allo storage ed è riusabile anche dai comandi. L'accesso HTTP alla scheda richiede il Gate `view`.

Le tre icone operative **Calcola SHA-256**, **Leggi UUID** e **Scrivi UUID** sono definite in `Helpers/Files/FileHelpersRegistry.php`. I relativi helper accettano un'Entry persistita e sono richiamabili singolarmente:

```php
use IlBronza\MediaManager\Helpers\Files\CalculateSha256Helper;
use IlBronza\MediaManager\Helpers\Files\ReadUuidHelper;
use IlBronza\MediaManager\Helpers\Files\WriteUuidHelper;

$observation = app(CalculateSha256Helper::class)->run($entry);
$identity = app(ReadUuidHelper::class)->run($entry);
$written = app(WriteUuidHelper::class)->run($entry);
```

Il calcolo restituisce hash osservato, hash registrato e risultato del confronto, senza aggiornare la baseline o lo stato. La lettura UUID non scrive metadati. La scrittura verifica contenuto, stato e identità corrente, riusa l'UUID dell'Entry e verifica la rilettura: un UUID diverso, un file modificato o uno storage senza supporto producono un errore. Nessun helper operativo accetta un percorso non registrato al posto dell'Entry. I processi del package sono coordinati tramite il lock del disco; come per le altre operazioni, gli scrittori esterni devono essere coordinati dall'applicazione.

Le icone inviano richieste POST protette da CSRF: letture con Gate `view`, scrittura con `view` e `manage`. I risultati vengono mostrati nel layout condiviso. Le stesse operazioni sono disponibili agli amministratori via Artisan:

```sh
php artisan media-manager:file-helper UUID-ENTRY sha256
php artisan media-manager:file-helper UUID-ENTRY read-uuid
php artisan media-manager:file-helper UUID-ENTRY write-uuid
```

L'output è JSON; exit code 1 indica un errore o un confronto non corrispondente. L'UUID richiesto è quello del catalogo, non l'ID Spatie.

## Prima importazione e API

```sh
php artisan media-manager:import archive
# oppure un singolo file, con indicizzazione automatica delle directory genitrici
php artisan media-manager:import archive clienti/rossi/contratto.pdf
```

L'importazione mantiene i file al loro posto. File nuovi vengono registrati con SHA-256 e, se disponibile, UUID negli xattr. Se sono rilevati riferimenti mancanti o cambiati, riconciliare prima di importare: l'importazione non deve trasformare un file spostato in un nuovo documento.

```php
use IlBronza\MediaManager\Services\{Catalog, Mover, Reconciler};

$catalog = app(Catalog::class);
$entry = $catalog->import('archive', 'clienti/rossi/contratto.pdf', [
    'scadenza' => '2027-01-01',
]);
$catalog->link($entry, $dossierrow, 'contratto');

$catalog->createDirectory('archive', 'archivio');
$operation = app(Mover::class)->move($entry, 'archivio/contratto.pdf');
$entry->refresh(); // stessa identità, nuovo path
```

Per aggiungere una relazione al model ospite si può usare `HasManagedMedia` e leggere `$model->managedMediaLinks()->with('entry')->get()`. I link sono polimorfici, con ruolo, e possono collegare più model allo stesso file. Scollegare un `MediaLink` non elimina il file.

## Identità e riconciliazione

`XattrIdentityStore` usa l'estensione PHP **PECL xattr**, se installata. L'attributo Linux è `user.ilbronza.media_uuid` (all'API PHP si passa `ilbronza.media_uuid`, perché aggiunge il namespace). Scrittura e rilettura verificano che il tag sia persistito. Senza estensione o supporto del filesystem il package funziona con SHA-256; `identity_tagged` resta falso. Non viene installata alcuna estensione né invocato un comando shell a runtime. Un diverso backend può implementare `Contracts\IdentityStore` ed essere configurato in `identity_store`.

```sh
# Anteprima; nessuna modifica ai record o ai file
php artisan media-manager:reconcile archive

# Applica solo spostamenti con UUID univoco e contenuto coerente;
# registra anche stati missing, modified, conflict e candidate
php artisan media-manager:reconcile archive --apply

# Selezione esplicita di un candidato senza UUID, con verifica SHA-256
php artisan media-manager:recover UUID-DEL-RECORD archivio/file-ritrovato.pdf
```

- UUID duplicati, destinazioni già occupate nel catalogo e identità contraddittorie generano conflitti.
- L'hash prova l'uguaglianza dei contenuti, non l'identità del documento: anche una corrispondenza singola richiede selezione esplicita.
- I file modificati non ricevono automaticamente una nuova impronta: occorre una decisione applicativa sulla sostituzione/versione.
- La scansione deve completarsi prima di aggiornare il catalogo. Una directory illeggibile interrompe l'operazione, evitando falsi mancanti.
- Il recupero è limitato ai dischi abilitati e accessibili. Gli spostamenti esterni fra dischi vengono segnalati, senza ricollegamento automatico.
- Le directory sono recuperabili automaticamente se conservano l'UUID; senza UUID i contenuti producono candidati, ma non viene dedotta automaticamente l'identità della directory.

Il rapporto contiene `entries`, `unindexed` e `duplicate_uuids`. Il comando restituisce exit code 1 in presenza di anomalie. La scansione completa legge i contenuti per calcolare gli hash: per archivi grandi pianificarla come job amministrativo. Non vengono installati scheduler o watcher automaticamente. La coerenza dopo modifiche esterne è eventuale, al completamento della riconciliazione.

## Spostamenti e recupero da interruzione

Ogni spostamento passa per `Mover`, acquisisce un lock per disco e registra una `Operation` prima di cambiare il filesystem. Gli ID e i link rimangono invariati.

Per i file si crea un hard link esclusivo nella destinazione (stesso filesystem), si verifica l'identità, si aggiorna il database e infine si rimuove il vecchio link. Una destinazione esistente non viene sovrascritta. Il filesystem deve supportare hard link. Per le directory si usa una rinomina nello stesso filesystem e si aggiornano i percorsi dei discendenti in transazione, dopo la verifica delle identità.

```sh
php artisan media-manager:resume UUID-OPERAZIONE
```

Il recupero distingue operazioni preparate e pulizia successiva al commit. Se sorgente o destinazione sono state sostituite, si ferma e conserva i dati. Le operazioni incomplete bloccano ulteriori mutazioni/scansioni applicative di quel disco finché non sono risolte. Consultare `media_manager_operations.error` in caso di errore.

I lock coordinano i processi del package. Un processo esterno può ignorarli: per rinominare directory in modo affidabile evitare scritture esterne concorrenti, perché PHP non offre una rinomina di directory portabile con garanzia atomica di non sovrascrittura. Le verifiche non equivalgono a una transazione distribuita né alla durabilità di un backup. Gli eventi `EntryRelocated` vengono emessi a operazione riuscita per cache e audit; i model risolvono il percorso tramite il catalogo, senza dipendere dalla consegna degli eventi.

## Spatie e File Cabinet

In `config/media-manager.php` impostare `'spatie' => true` quando Spatie Media Library v10 o v11 è installato. Il bridge riconosce l'evento e il binding standard del filesystem di entrambe le versioni; l'integrazione con Spatie 10.15 è verificata nei test di Padovanio. Il model configurato in `media-library.media_model` può rimanere `IlBronza\CRUD\Models\Media`. Media e catalogo devono usare la stessa connessione al database. L'opzione Spatie `moves_media_on_update` mantiene il suo comportamento per i media non gestiti; per quelli già registrati si conserva la posizione effettiva persistita.

Con `auto_adopt => true`, un listener registra i nuovi file dopo `MediaHasBeenAddedEvent`, quando File Cabinet/Spatie ha già scelto la destinazione e scritto il file. Il listener non decide il percorso e non modifica le impostazioni File Cabinet. Un errore di indicizzazione viene segnalato nei log senza annullare il caricamento: il media rimane adottabile successivamente. Gli inserimenti esistenti possono essere registrati esplicitamente:

```sh
php artisan media-manager:adopt ID-MEDIA-SPATIE
```

Oppure:

```php
$entry = app(\IlBronza\MediaManager\Integrations\Spatie\Bridge::class)->adopt($media);
```

L'adozione crea un record di catalogo associato al media esistente e collega il proprietario. Conserva ID, UUID Spatie, owner, collection e proprietà personalizzate dell'applicazione. Il catalogo ha un proprio UUID persistente. Non viene creata una seconda copia del file e non viene sostituito il model Spatie.

La posizione effettiva viene salvata **anche sul record `media`**, nel namespace riservato `custom_properties.media_manager.location`, con `disk`, `directory`, `file_name`, `path`, UUID del catalogo e percorsi dei derivati. Mover e riconciliazione aggiornano catalogo e posizione del media nella stessa transazione. Questo valore riguarda esclusivamente quel media; i caricamenti futuri continuano a consultare le regole File Cabinet.

Il bridge fa risolvere `getPath()`, `getPathRelativeToRoot()`, `getUrl()`, la directory del PathGenerator e lo stream delle conversioni dalla posizione effettiva. Dopo uno spostamento, anche un'istanza Spatie già caricata risolve la nuova posizione. Quando si rinomina dal Media Manager, **`Media::file_name` conserva il nome originale Spatie** per mantenere stabili i nomi delle conversioni; il nome fisico corrente è `$entry->name` e viene persistito in `custom_properties.media_manager.location.file_name`. Le directory delle conversioni/responsive images vengono memorizzate all'adozione: spostare il solo originale le lascia dove sono; spostare una directory aggiorna anche i riferimenti ai derivati al suo interno.

I generatori di percorsi preesistenti vengono mantenuti come fallback per media non adottati. URL generator o binding Filesystem personalizzati richiedono un adattatore esplicito: il package interrompe il boot invece di sovrascriverli silenziosamente. I job esterni di generazione/conversione devono essere coordinati con gli spostamenti di directory.

Il ciclo di vita di File Cabinet resta attivo: può svuotare una collection, sostituire o cancellare un media. Se il file è stato spostato, la rimozione usa la posizione corrente e rimuove anche il relativo record di catalogo. Si eliminano solo i file esatti del media e i derivati registrati, preservando gli altri file nella stessa directory. I soft delete continuano a seguire il comportamento del model Spatie configurato. Non sono previsti blocchi generali a queste operazioni.

Se un nuovo upload scrive esattamente sul percorso di un media precedente, l'evento di inserimento registra una nuova identità e marca la precedente come `superseded`. Il vecchio record conserva l'ultima posizione per lo storico, ma non occupa più il percorso nell'indice attivo. Questo copre anche `IlBronza\CRUD\Models\Media` con soft delete: un successivo `forceDelete()` del vecchio media non elimina il file sostitutivo. Le nuove installazioni creano direttamente la chiave del percorso nullable. La migrazione `000002` aggiorna le installazioni precedenti e salta il cambio di colonna se lo schema è già aggiornato; eseguire nuovamente `php artisan migrate` se il prototipo precedente era già stato installato.

Esempio verificato: File Cabinet salva in `clienti/rossi/file.txt`; Media Manager lo sposta in `archivio/spostato.txt` e aggiorna il record media; un nuovo allegato della stessa riga torna in `clienti/rossi/file.txt`. Anche un cambio della regola File Cabinet o del disk selezionato si applica al nuovo caricamento, senza ereditare lo spostamento manuale del file precedente.

**Un'interfaccia autonoma di cancellazione nel Media Manager, cestino, trasferimenti tra dischi e versionamento non fanno parte di questa versione.** Non eseguire `media-library:clean` sui dischi gestiti: la sua nozione di file orfano non coincide con il catalogo.

Se il proprietario è una `Dossierrow`, `FileCabinetContext` risale al dossier e ne mostra il link solo con Gate `media-manager.view-dossier` autorizzato. Per righe legacy con un percorso testuale, importare quel percorso e usare `Catalog::link($entry, $row, 'filecabinet')`. I vecchi controller che leggono direttamente `$row->file` devono essere aggiornati nell'app ospite per risolvere il file tramite il link al catalogo. Questo package non modifica implicitamente le colonne legacy o i controller di File Cabinet.

## Verifica

```sh
composer test
# Nel workspace IlBronza, usando il vendor condiviso:
python3 tests/run_shared.py
```

`run_shared.py` usa il runner `.ibtest`, spostando soltanto il bootstrap generato dentro questo package. Se WebAssembly non può avviare il socket locale, usare il PHP nativo già disponibile e le stesse dipendenze, senza installazioni:

```sh
php ../.ibtest/vendor/phpunit/phpunit/phpunit -c phpunit.xml --do-not-cache-result
```

La suite verifica database SQLite e filesystem reali, autorizzazioni HTTP, recupero da interruzioni, percorsi Spatie, più disk e conflitti. La regressione File Cabinet usa il suo vero `FilecabinetMediaPathGenerator` con una riga di test e veri upload/collection Spatie, verificando inserimento → spostamento → sostituzione, oltre allo svuotamento della collection prima di ricaricare. Questi test vengono saltati se il package opzionale FileCabinet non è disponibile; non sostituiscono una prova completa dei controller dell'applicazione ospite. Gli xattr sono simulati con un backend basato sull'inode per testare rename/copie in modo deterministico: il backend PECL va verificato sul filesystem di produzione. Su PHP 8.5 il vecchio template Testbench/Laravel 11 segnala due deprecazioni `PDO::MYSQL_ATTR_SSL_CA`, esterne al codice del package.
