Object reference
================

This is a reference of all objects referred to by the API documentation.

.. _api-poi-object:

POI object
----------

An object which represents a single Pokéstop.

id
   The internal integer ID of the Pokéstop.

name
   The human-readable name of the Pokéstop.

latitude
   The latitude coordinate of the Pokéstop.

longitude
   The longitude coordinate of the Pokéstop.

objective
   An `Fully-defined objective object`_.

reward
   A `Fully-defined reward object`_.

updated
   An `Update object`_.

Example
^^^^^^^

.. code-block:: json

   {
       "id": 2,
       "name": "Statue of Liberty",
       "latitude": 40.68925377062,
       "longitude": -74.044514894485,
       "objective": {
           "type": "battle_gym",
           "params": {
               "quantity": 1
           }
       },
       "reward": {
           "type": "pinap_berry",
           "params": {
               "quantity": 5
           }
       },
       "updated": {
           "on": 1553450414,
           "by": {
               "nick": "bilde2910",
               "color": "#008040"
           }
       }
   }

.. _api-objective-object:

Fully-defined objective object
------------------------------

An object which represents all data about a particular research objective.

type
   A valid objective type, as defined in `objectives.yaml
   <https://github.com/bilde2910/FreeField/blob/master/includes/data/objectives.yaml>`_.
   Example: `win_raid`.

params
   A key-value object representing data for the objective type specified in
   ``type``. The list of parameters for each objective type are specified in the
   objectives.yaml file. See :ref:`parameter-list` for a complete list of all
   supported parameters.

Example
^^^^^^^

.. code-block:: json

   {
       "type": "win_raid",
       "params": {
           "quantity": 1
       }
   }

.. _api-match-objective-object:

Best-match objective object
---------------------------

An object which contains a string to be paired in the best possible way to a
fully defined objective.

match
   A string containing a human-readable representation of the objective.

match_algo *(optional, default=2)*
   The algorithm to be used for pairing the objective:

   1
      Match against objectives found in `common-tasks.yaml
      <https://github.com/bilde2910/FreeField/blob/master/includes/data/common-tasks.yaml>`_
      only. Matches very quickly, but can be inaccurate, particularly if
      attempting to match new objectives against an outdated common objectives
      list.

   2
      Match against all possible objectives defined in objectives.yaml. Highly
      accurate, but much slower than algorithm 1.

Example
^^^^^^^

.. code-block:: json

   {
       "match": "Make 5 Great Curveball Throws in a row",
       "match_algo": 2
   }

.. _api-reward-object:

Fully-defined reward object
---------------------------

An object which represents all data about a particular research reward.

type
   A valid reward type, as defined in `rewards.yaml
   <https://github.com/bilde2910/FreeField/blob/master/includes/data/rewards.yaml>`_.
   Example: `potion`.

params
   A key-value object representing data for the reward type specified in
   ``type``. The list of parameters for each reward type are specified in the
   rewards.yaml file. See :ref:`parameter-list` for a complete list of all
   supported parameters.

Example
^^^^^^^

.. code-block:: json

   {
       "type": "encounter",
       "params": {
           "species": [
               56,
               66
           ]
       }
   }

.. _api-match-reward-object:

Best-match reward object
------------------------

An object which contains a string to be paired in the best possible way to a
fully defined reward.

match
   A string containing a human-readable representation of the reward.

match_algo *(optional, default=2)*
   The algorithm to be used for pairing the reward:

   2
      Match against all possible rewards defined in rewards.yaml.

Example
^^^^^^^

.. code-block:: json

   {
       "match": "3 Potions",
       "match_algo": 2
   }

.. _api-update-object:

Update object
-------------

An object which contains details about when and who last updated something.

on
   A UNIX timestamp representing the time of update.

by
   A `User object`_.

Example
^^^^^^^

.. code-block:: json

   {
       "on": 1553450212,
       "by": {
           "nick": "bilde2910",
           "color": "#008040"
       }
   }

.. _api-user-object:

User object
-----------

An object which contains data about a user.

nick
   The nickname of the user.

color
   The display color of the user, as determined by group membership.

Example
^^^^^^^

.. code-block:: json

   {
       "nick": "bilde2910",
       "color": "#008040"
   }

.. _api-location-object:

Location object
---------------

An object that specifies a particular location.

latitude
   The latitude coordinate of the location.

longitude
   The longitude coordinate of the new location.

Example
^^^^^^^

.. code-block:: json

   {
       "latitude": 40.68925377062,
       "longitude": -74.044514894485
   }
