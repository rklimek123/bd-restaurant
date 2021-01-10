# bd-restaurant
Restaurant. Project for Databases course

Schemat reprezentuje działanie restauracji.

W bazie trzymane są informacje o daniach w menu, wymaganych do nich składnikach w określonych ilościach, ilościach składników na stanie.

Użytkownik jest podzielony na pracownika restauracji i klienta. Pracownik różni się tym, że nie ma przypisanego adresu.

Pracownik może przeglądać i zmieniać stan składników na magazynie, dodawać/usuwać pozycje w menu przeglądać historię zamówień, zmieniać status zamówień (wznawiać, anulować, dostarczać).

Status zamówienia:
* w trakcie:    flgActive = 1 && arrived at IS NULL
* dostarczone:	flgActive = 0 && arrived at IS NOT NULL
* anulowane:	  flgActive = 0 && arrived at IS NULL


Klient ma wgląd do menu, może wykonać zamówienie. Wybiera dania, które chce zamówić i zatwierdza zamówienie. W wyborze w menu jest informacja o braku składników potrzebnych na zrobienie danego dania, nie pozbawia to jednak klienta możliwości dodania tego dania do koszyka. Dopiero przy zatwierdzeniu zamówienia (gdzie też jest ta informacja dla każdego dania) sprawdzana jest dostępność składników i opcjonalnie wyskakuje informacja, że nie można wykonać tego zamówienia. Jeśli można wykonać zamówienie, odpowiednia ilość składników jest usuwana ze stanu magazynu.

Klient ma wgląd do swoich zamówień, nie może jednak zmieniać ich statusu.

Klient może zmieniać swój adres, ale w historii zamówień adres pamiętany jest osobno, nie ma to więc wpływu na historię.

Każdy użytkownik ma możliwość zmiany swoich danych lub usunięcie konta. Usunięcie konta nie usuwa żadnych informacji z historii.
