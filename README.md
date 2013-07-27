
Dette er en liten samling webtjenester som for bor på [services.biblionaut.net](http://services.biblionaut.net). 
Tjenestene tilbyr ikke egne data, men bearbeider data fra andre kilder.

- [sru_iteminfo.php](public_html/sru_iteminfo.php) gir resultater fra søk mot SRU- eller z39.50-servere i JSON-format. Resultatene bearbeides med en enkel MARC-parser.
- [bibsys_holdings.php](public_html/bibsys_holdings.php) gir beholdningsinformasjon for et objekt fra BIBSYS i JSON-format
- [getids.php](public_html/getids.php) er en wrapper for BIBSYS' [getIds-tjeneste](http://adminwebservices.bibsys.no/objectIdService/getIds), som returnerer tilknyttede ID-er i BIBSYS, typisk objektid, dokid og/eller heftid for knyttid. I tillegg returnerer scriptet objektid for isbn-nummer.
- [content.php](public_html/content.php) er en wrapper for BIBSYS' [tjeneste for innholdsinformasjon og omslagsbilde](http://blogs.bibsys.no/discovery/2013/06/17/service-for-content-information-cover-images/) som gir resultater i JSON-format
