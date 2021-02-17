# Data Against COVID-19/FR in RDF/SPARQL

[Data Against COVID-19/FR](https://opencovid19-fr.github.io/) is an informal French organization including people from the civil society and spanning multiple domains and skills. Its goal is to provide consolidated data and visualization tools about the evolution of the COVID-19 pandemic in France.

The [Wimmics team](https://team.inria.fr/wimmics/) and [I3S laboratory](http://www.i3s.unice.fr/) (University Côte d'Azur, Inria, CNRS) provide an [RDF](https://en.wikipedia.org/wiki/Resource_Description_Framework) representation of these data, comprising the daily numbers of confirmed cases, hospitalized cases, cases in intensive care, recoveries and deaths, for France and its regions and departments.


### SPARQL Querying

The data is published on our Virtuoso OS SPARQL endpoint: https://covidontheweb.inria.fr/sparql, as a named graph `http://ns.inria.fr/covid19/graph/opencovid19fr` updated daily.

As an example, you can look up the announcement http://ns.inria.fr/covid19/opencovid19fr/announcement/DEP-75/2020-08-19 concerning Paris on Aug. 19th 2020, or [submit the SPARQL query below](https://covidontheweb.inria.fr/sparql?default-graph-uri=&query=select+*+from+%3Chttp%3A%2F%2Fns.inria.fr%2Fcovid19%2Fgraph%2Fopencovid19fr%3E%0D%0Awhere+{%0D%0A++++%3Fa++a+schema%3ASpecialAnnouncement%3B%0D%0A++++++++schema%3AdatePosted+%3Fdate%3B%0D%0A++++++++schema%3AspatialCoverage+[%0D%0A++++++++++++a++++++++++++++++++++wd%3AQ6465%3B+%23+department%0D%0A++++++++++++schema%3Aname++++++++++%3FlocationName%3B%0D%0A++++++++++++schema%3Aidentifier++++%2275%22%3B%0D%0A++++++++]%3B%0D%0A++++++++schema%3AdiseaseSpreadStatistics+[%0D%0A++++++++++++rdfs%3Alabel+++++++++++%3FstatLabel%3B%0D%0A++++++++++++schema%3AmeasuredValue+%3FstatVal%0D%0A++++++++].%0D%0A}+order+by+desc(%3Fdate)) that retrieves all announcements for Paris (department 75):

```sparql
select * from <http://ns.inria.fr/covid19/graph/opencovid19fr>
where {
    ?a  a schema:SpecialAnnouncement;
        schema:datePosted ?date;
        schema:spatialCoverage [
            a                    wd:Q6465; # department
            schema:name          ?locationName;
            schema:identifier    "75";
        ];
        schema:diseaseSpreadStatistics [
            rdfs:label           ?statLabel;
            schema:measuredValue ?statVal
        ].
} order by desc(?date)
```

For a visual exploration, check the [Jupiter Notebook](jupyter_notebok_example.ipynb) we provide.


### RDF Data Modeling

The RDF representation is based on the [Schema.org extention](http://blog.schema.org/2020/03/schema-for-coronavirus-special.html) defined recently for the case of the COVID-19 pandemic.

Each daily report is an announcement ([`schema:SpecialAnnouncement`](https://schema.org/SpecialAnnouncement)) that has:
- a geographic zone where the figures apply (`schema:spatialCoverage`), whose type is one of [`schema:State`](http://schema.org/State]) and Wikidata entities [region of France](http://www.wikidata.org/entity/Q36784), [department of France](http://www.wikidata.org/entity/Q6465), [overseas collectivity](http://www.wikidata.org/entity/Q719487), [world](http://www.wikidata.org/entity/Q16502)
- a source (`schema:sourceOrganization` and `schema:url`)
- numbers of cases (confirmed, hospitalized, in intensive care, etc) are [`schema:Observation`](https://schema.org/Observation)'s provided with property [`schema:diseaseSpreadStatistics`](https://schema.org/diseaseSpreadStatistics).


### Data Transformation

_Data against COVID-19/FR_  provides the data as a JSON file updated daily.

The mapping of this JSON file to RDF is achieved using a [SPARQL micro-service](https://github.com/frmichel/sparql-micro-service) accessible at https://sparql-micro-services.org/service/opencovid19fr/dailyCases_sd.

We have set up an automated process to invoke this service daily and update the graph in our SPARQL endpoint.
