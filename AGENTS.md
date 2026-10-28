# Convenzioni Media Manager

- Usare UIkit e il layout condiviso dell'applicazione (`uikittemplate::app` per default).
- Catalogo e Filesystem sono due viste distinte con lo stesso layout: navigazione a sinistra, contenuto a destra.
- Costruire le tabelle con `IlBronza\Datatables\Datatables`, come CRUD. Dichiarare i campi negli array dei `FieldsGroupParametersFile`, configurati con `models.*.fieldsGroupsFiles`.
- Privilegiare leggibilità e manutenzione: metodi PHP in stile Allman, spaziature chiare, query esplicite e leggibili. Evitare istruzioni multiple sulla stessa riga.
- Collocare gli helper nel namespace `IlBronza\MediaManager\Helpers`, separati per responsabilità. Gli helper operativi devono funzionare sul singolo file e poter essere richiamati anche da Artisan senza dipendere da request, sessione o view.
- Le operazioni di gestione richiedono un'Entry persistita. La navigazione fisica può leggere file non indicizzati, senza registrarli o scrivere UUID automaticamente.
- File Cabinet decide destinazione e comportamento di ogni nuovo upload. Spostare un media esistente non cambia le regole degli inserimenti successivi.
- Uno SHA diverso non crea un nuovo media e non sostituisce automaticamente l'impronta registrata. Scrivere l'UUID riusa l'ID esistente e non sovrascrive identità diverse.
- I test usano il vendor condiviso `.ibtest` senza installare dipendenze. In questo ambiente funziona anche `/opt/homebrew/bin/php ../.ibtest/vendor/phpunit/phpunit/phpunit -c phpunit.xml --do-not-cache-result`.
