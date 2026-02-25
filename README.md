
# Zmiany w kodzie

- `AbstractAzureClient.php` 
    - zmieniłem logikę retry aby działała również dla kodu 429 i aby uwzględniał czas podany w odpowiedzi
    - wyodrębniłem serwis do obsługi requestów HTTP
    - dodałem metryki a z wasadzie logowanie informacji, aby można było monitorować działanie klienta
    - dodałem walidacje body
    - wyciągłem inicjalizacje curla z petli retry
    - wyodrębniłem stałe i parametry konstruktora

- `AzureSearchIndexClient.php` 
    - uprościłem wywołanie requestów 
    - dodałem sprawdzanie pól w body requestu
    - usunołem nie uzywany kod jak destrukturyzacja wartości zwracanej a nie używanej
    - dodałem dzielenie na chunki aby móc obsłużyć dużą liczbę dokumentów
    - wydzieliłem parametry konstruktora i stałą

- `Product.php`
    - zmieniłem sposób tworzenia wartości dla hashy - łączenie separatorem aby wyeliminować potencjalnie te same wartości
    - dodałem walidacje znormalizwanego id    

## Krótkie uzasadnienie zmian    

Moje zmiany w kodzie miały głónie na celu poprawę błędów i niezawodność działania. Założyłem że kod musi być oparty o metodyke Fail First ponieważ odpytuje zewnętrzny serwis, więc jeżeli coś pójdzie nie tak to program powinien się zatrzymać i poinformować o błędzie. Dodatkowo skupiłem się na wydzieleniu komponentow z klas aby można było bardziej szcegółowo testować działanie - dzieki temu możemy badać edge case'y. Dodałem phpunitesty żeby na berząco sprawdzać zmiany w kodzie. 

## Co bym zmienił

Wybrałbym pewnie symfony i skorzystał napewno z Messenger'a aby dodać asynchroniczności oraz RabitMQ aby przenieść logikę retry na dedykowane kolejki. Użyłbym pewnie validatoró i obiektów DTO do walidacji i wymiany danych. Użyłbym istniejącego klienta http z symfony zamiast pisać własną nakładkę na curl'a. 

