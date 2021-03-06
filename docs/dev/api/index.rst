API Reference
=============

FreeField has a RESTful API for third-party developers to interface with their
instances. This document serves as the reference for this API.

Authentication
--------------

All requests to the API must be authenticated using an access token. To obtain
an access token, go to the "API access" section of the administration pages and
click on :guilabel:`Add new client`.

1. Define a name for your client. This name will be publicly visible when the
   client adds, modifies and deletes Pokéstops and other resources using the
   API. The client name is displayed in lieu of a user account's nickname where
   such a nickname would otherwise be shown.

2. Optionally select a color for your client to accompany its name when
   displayed.

3. Click in the "Permissions" column for your client to define access controls
   for your client.

   -  Check all permissions in the permissions list that your client needs to
      operate.

   -  Set a reasonable permission level for the client's capability of
      administering FreeField. For example, if your client has permission to
      manage and delete user accounts in your FreeField instance, the client can
      do so only for users up to and including the given permission level. The
      permission level is exclusively used for this purpose - permissions set on
      the "Permissions" section of the administration interface are overridden
      by the permissions specified for your client under "API settings." The
      permission level setting is currently not in use, but will be used in a
      future version of FreeField.

   Click on :guilabel:`Save settings` when you are done.

4. Click :guilabel:`Save settings` when you are done to generate an access
   token.

5. In the "Access token" column, click on :guilabel:`Click to view` to view and
   copy the access token for your client.

**To authenticate your client against the API, set the** ``X-Access-Token``
**header of every request to the access token from step 5 above.**

Response format
---------------

All requests to the API will return some kind of response. The response is sent
as an object using ``Content-Type: application/json``. Responses will use an
appropriate HTTP response code depending on the success state of the request.
Clients should treat all 2xx status codes as a successful request, all 4xx
status codes as a client error, and all 5xx status codes as a server error.

.. note:: Requests resulting in a 204 No Content status code will not have a
          response body.

All responses that have a JSON body will have the field ``ff_version``
indicating the version number of the current FreeField installation. All error
codes (4xx, 5xx) will additionally have a ``reason`` field containing an error
code. Error codes and their meaning are explained in detail for each request
type under `Method reference`_.

Object reference
----------------

This is a reference of all objects referred to by the API documentation.

.. toctree::
   :maxdepth: 2

   objects

Method reference
----------------

This is a reference of all methods available to call in the API.

.. toctree::
   :maxdepth: 2

   manage-poi
