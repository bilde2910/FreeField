Managing Pokéstops
==================

Listing all Pokéstops
---------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  GET
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

``updatedSince`` *(optional)*
   A UNIX timestamp, or a delta-time difference in seconds.

   **Examples:**

   -  ``1546300800``

      Lists all Pokéstops that were updated since midnight on Jan 1, 2019.

   -  ``-120``

      Lists all Pokéstops that were updated in the last two minutes.

Response
^^^^^^^^

ff_version
   The version of the FreeField instance.

pois
   An array of :ref:`api-poi-object`.

id_list
   An array of integers representing the IDs of all Pokéstops on the map.


Example
^^^^^^^

Request
"""""""

.. code-block:: http

   GET /api/poi.php?updatedSince=1546300800 HTTP/1.0
   X-Access-Token: <access_token>

Response
""""""""

.. code-block:: http

   HTTP/1.1 200 OK
   Content-Type: application/json

.. code-block:: json

   {
      "ff_version": "1.1-rc.1",
      "pois": [
           {
               "id": 1,
               "name": "Central Park",
               "latitude": 40.781386328336,
               "longitude": -73.967599868774,
               "objective": {
               "type": "catch_type",
                  "params": {
                      "type": [
                          "electric",
                          "normal",
                          "poison"
                      ],
                      "quantity": 5
                  }
               },
               "reward": {
                   "type": "encounter",
                   "params": {
                       "species": [
                           56,
                           66
                       ]
                   }
               },
               "updated": {
                   "on": 1553450212,
                   "by": {
                       "nick": "bilde2910",
                       "color": "#008040"
                   }
               }
           },
           {
               "id": 2,
               "name": "Statue of Liberty",
               "latitude": 40.68925377062,
               "longitude": -74.044514894485,
               "objective": {
                   "type": "unknown",
                   "params": []
               },
               "reward": {
                   "type": "unknown",
                   "params": []
               },
               "updated": {
                   "on": 1550957320,
                   "by": {
                       "nick": "bilde2910",
                       "color": "#008040"
                   }
               }
           }
       ],
       "id_list": [
           1,
           2
       ]
   }

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

Reporting field research
------------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  PATCH
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

Pokéstop identifier
   Must one of the following sets of arguments. The first found identifier is
   used among those listed below.

   Match by ID
      Matches exactly against a single, well-known Pokéstop using its ID.

      ``id``
         The unique numerical ID of the Pokéstop in FreeField's database.

   Match by location
      Matches against the Pokéstop that is closest to the given location.

      ``latitude``
         The latitude coordinate of the Pokéstop.

      ``longitude``
         The longitude coordinate of the Pokéstop.

   Match by name
      Attempts to find the best matching name out of all Pokéstops on the map.

      ``name``
         The name of the Pokéstop.

      ``match_exact`` *(optional, default=false)*
         ``true`` if the name must be matched exactly, ``false`` otherwise.

      ``match_case`` *(optional, default=true)*
         ``true`` if the name is case sensitive, ``false`` otherwise.

``objective``
   A :ref:`api-objective-object` or a :ref:`api-match-objective-object`.

``reward``
   A :ref:`api-reward-object` or a :ref:`api-match-reward-object`.

Response
^^^^^^^^

*Empty.*

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   PATCH /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty",
       "objective": {
           "match": "Make 5 Great Curveball Throws in a row",
           "match_algo": 2
       },
       "reward": {
           "match": "3 Potions"
       }
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 204 No Content

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

invalid_data
   The objective or reward objects you provided are malformed.

match_mode_not_implemented
   You specified an invalid match_algo for the objective or reward.

missing_fields
   You're missing either a Pokéstop identifier, an objective and/or a reward.

no_poi_candidates
   Your Pokéstop identifier did not match any Pokéstops in the database (e.g.
   supplying the ID of a Pokéstop which does not exist, or has been deleted).

poi_ambiguous
   Your Pokéstop identifier matched several Pokéstops equally well. A list of
   POI IDs for these are provided in ``candidates`` alongside this error
   response.

Clearing research from Pokéstop
-------------------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  PATCH
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

Pokéstop identifier
   Must one of the following sets of arguments. The first found identifier is
   used among those listed below.

   Match by ID
      Matches exactly against a single, well-known Pokéstop using its ID.

      ``id``
         The unique numerical ID of the Pokéstop in FreeField's database.

   Match by location
      Matches against the Pokéstop that is closest to the given location.

      ``latitude``
         The latitude coordinate of the Pokéstop.

      ``longitude``
         The longitude coordinate of the Pokéstop.

   Match by name
      Attempts to find the best matching name out of all Pokéstops on the map.

      ``name``
         The name of the Pokéstop.

      ``match_exact`` *(optional, default=false)*
         ``true`` if the name must be matched exactly, ``false`` otherwise.

      ``match_case`` *(optional, default=true)*
         ``true`` if the name is case sensitive, ``false`` otherwise.

``reset_research``
   Flag that specifies that you want to clear research for this Pokéstop. Set to
   any value, ``true`` is a reasonable choice.

Response
^^^^^^^^

*Empty.*

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   PATCH /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty",
       "reset_research": true
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 204 No Content

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

missing_fields
   You're missing a Pokéstop identifier or the ``reset_research`` flag.

no_poi_candidates
   Your Pokéstop identifier did not match any Pokéstops in the database (e.g.
   supplying the ID of a Pokéstop which does not exist, or has been deleted).

poi_ambiguous
   Your Pokéstop identifier matched several Pokéstops equally well. A list of
   POI IDs for these are provided in ``candidates`` alongside this error
   response.

Add a new Pokéstop
------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  PUT
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

``name``
   The name of the Pokéstop.

``lat``
   The latitude of the Pokéstop.

``lon``
   The longitude of the Pokéstop.

Response
^^^^^^^^

ff_version
   The version of the FreeField instance.

poi
   A :ref:`api-poi-object`.

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   PUT /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty",
       "lat": 40.68925377062,
       "lon": -74.044514894485
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 201 Created
   Content-Type: application/json

.. code-block:: json

   {
       "ff_version": "1.1-rc.1",
       "poi": {
           "id": 2,
           "name": "Statue of Liberty",
           "latitude": 40.68925377062,
           "longitude": -74.044514894485,
           "objective": {
               "type": "unknown",
               "params": []
           },
           "reward": {
               "type": "unknown",
               "params": []
           },
           "updated": {
               "on": 1550957320,
               "by": {
                   "nick": "bilde2910",
                   "color": "#008040"
               }
           }
       }
   }

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

invalid_location
   The latitude and longitude coordinate pair you provided is outside the
   Pokéstop geofence. See :ref:`limit-poi-bounds` for more information.

missing_fields
   You're missing a name, latitude or longitude.

name_empty
   The Pokéstop name you provided was empty.

Moving a Pokéstop
-----------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  PATCH
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

Pokéstop identifier
   Must one of the following sets of arguments. The first found identifier is
   used among those listed below.

   Match by ID
      Matches exactly against a single, well-known Pokéstop using its ID.

      ``id``
         The unique numerical ID of the Pokéstop in FreeField's database.

   Match by location
      Matches against the Pokéstop that is closest to the given location.

      ``latitude``
         The latitude coordinate of the Pokéstop.

      ``longitude``
         The longitude coordinate of the Pokéstop.

   Match by name
      Attempts to find the best matching name out of all Pokéstops on the map.

      ``name``
         The name of the Pokéstop.

      ``match_exact`` *(optional, default=false)*
         ``true`` if the name must be matched exactly, ``false`` otherwise.

      ``match_case`` *(optional, default=true)*
         ``true`` if the name is case sensitive, ``false`` otherwise.

``move_to``
   A :ref:`api-location-object`.

Response
^^^^^^^^

*Empty.*

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   PATCH /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty",
       "move_to": {
           "latitude": 40.68925377062,
           "longitude": -74.044514894485
       }
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 204 No Content

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

invalid_data
   The latitude and longitude coordinate pair you provided is invalid.

invalid_location
   The latitude and longitude coordinate pair you provided is outside the
   Pokéstop geofence. See :ref:`limit-poi-bounds` for more information.

missing_fields
   You're missing a Pokéstop identifier or the new location.

no_poi_candidates
   Your Pokéstop identifier did not match any Pokéstops in the database (e.g.
   supplying the ID of a Pokéstop which does not exist, or has been deleted).

poi_ambiguous
   Your Pokéstop identifier matched several Pokéstops equally well. A list of
   POI IDs for these are provided in ``candidates`` alongside this error
   response.

Renaming a Pokéstop
-------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  PATCH
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

Pokéstop identifier
   Must one of the following sets of arguments. The first found identifier is
   used among those listed below.

   Match by ID
      Matches exactly against a single, well-known Pokéstop using its ID.

      ``id``
         The unique numerical ID of the Pokéstop in FreeField's database.

   Match by location
      Matches against the Pokéstop that is closest to the given location.

      ``latitude``
         The latitude coordinate of the Pokéstop.

      ``longitude``
         The longitude coordinate of the Pokéstop.

   Match by name
      Attempts to find the best matching name out of all Pokéstops on the map.

      ``name``
         The name of the Pokéstop.

      ``match_exact`` *(optional, default=false)*
         ``true`` if the name must be matched exactly, ``false`` otherwise.

      ``match_case`` *(optional, default=true)*
         ``true`` if the name is case sensitive, ``false`` otherwise.

``rename_to``
   The new name for the Pokéstop.

Response
^^^^^^^^

*Empty.*

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   PATCH /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty",
       "rename_to": "Giant Statue"
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 204 No Content

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

missing_fields
   You're missing a Pokéstop identifier or the new name of the Pokéstop.

no_poi_candidates
   Your Pokéstop identifier did not match any Pokéstops in the database (e.g.
   supplying the ID of a Pokéstop which does not exist, or has been deleted).

poi_ambiguous
   Your Pokéstop identifier matched several Pokéstops equally well. A list of
   POI IDs for these are provided in ``candidates`` alongside this error
   response.

Deleting a Pokéstop
-------------------

.. list-table::
   :widths: 1 3

   *  -  Method
      -  DELETE
   *  -  URL
      -  /api/poi.php
   *  -  Content-Type
      -  application/json
   *  -  Since
      -  v1.1

Arguments
^^^^^^^^^

Pokéstop identifier
   Must one of the following sets of arguments. The first found identifier is
   used among those listed below.

   Match by ID
      Matches exactly against a single, well-known Pokéstop using its ID.

      ``id``
         The unique numerical ID of the Pokéstop in FreeField's database.

   Match by location
      Matches against the Pokéstop that is closest to the given location.

      ``latitude``
         The latitude coordinate of the Pokéstop.

      ``longitude``
         The longitude coordinate of the Pokéstop.

   Match by name
      Attempts to find the best matching name out of all Pokéstops on the map.

      ``name``
         The name of the Pokéstop.

      ``match_exact`` *(optional, default=false)*
         ``true`` if the name must be matched exactly, ``false`` otherwise.

      ``match_case`` *(optional, default=true)*
         ``true`` if the name is case sensitive, ``false`` otherwise.

Response
^^^^^^^^

*Empty.*

Example
^^^^^^^

Request
"""""""

.. code-block:: http

   DELETE /api/poi.php HTTP/1.0
   X-Access-Token: <access_token>
   Content-Type: application/json

.. code-block:: json

   {
       "name": "Statue of Liberty"
   }

Response
""""""""

.. code-block:: http

   HTTP/1.1 204 No Content

Errors
^^^^^^

access_denied
   Permission has not been granted to your client, or you are not properly
   authenticating with the API.

database_error
   A server-side issue is preventing this request from being fulfilled.

missing_fields
   You're missing a Pokéstop identifier.

no_poi_candidates
   Your Pokéstop identifier did not match any Pokéstops in the database (e.g.
   supplying the ID of a Pokéstop which does not exist, or has been deleted).

poi_ambiguous
   Your Pokéstop identifier matched several Pokéstops equally well. A list of
   POI IDs for these are provided in ``candidates`` alongside this error
   response.
