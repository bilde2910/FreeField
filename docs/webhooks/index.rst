Webhooks
========

A core component of research reporting is that community members are alerted to
research tasks of interest. This can be accomplished with webhooks. A webhook
is a handler for web requests that triggers some kind of action when called -
e.g. a message can be sent in a channel on Discord, or an alert triggered in a
Telegram group.

Webhooks can be set up from the "Webhooks" section on the administration pages.
There are built-in presets for some types of webhooks, but in principle, any
service can be the target of a webhook if the service supports setting up
webhook endpoints for inbound requests.

Webhook basics
--------------

All webhooks share some common principles - when field research is reported on
a Pokéstop, FreeField will evaluate the trigger conditions of all registered
webhooks, and if the reported research matches all the defined conditions, the
webhook is triggered. Every webhook has a target and a payload, and if the
webhook is triggered, FreeField connects to the target and sends the payload.

Webhooks can be added from the "Webhooks" section on the administration pages by
clicking on :guilabel:`Add new webhook`. A dialog box will open, prompting you
for the type of webhook to add, and if you wish to use a payload preset.

.. tip:: Payload presets are pre-configured payloads that, if selected, will
         automatically fill in the payload for your webhook. This can save you
         from having to configure your webhook's payload from scratch according
         to the documentation for the service that you are setting up a webhook
         for. Payload presets are described in greater detail under `Presets`_.

.. hint:: You can refer to the documentation for `Common webhook targets`_ below
          to see what webhook types and payload presets you should choose for
          the most common webhook target services. You should refer to this
          documentation before you create your webhook.

Here is a general overview of each webhook type:

Post JSON
^^^^^^^^^

This type of webhook will make an HTTP POST request to the given target URL with
the specified payload. The payload is encoded as JSON, with enforced syntax
validation. If you enter invalid JSON in the payload field of the webhook, you
will not be able to save the webhook settings.

.. hint:: This is the most common type of webhook, and most webhook endpoints
          support this type of request.

.. tip:: In addition to FreeField's built-in JSON validation, you can use a
         third party service such as `JSONLint <https://jsonlint.com/>`_ to
         validate the JSON payload.

Requests sent with this webhook type will have the following HTTP headers:

.. parsed-literal::
   Content-Type: application/json
   User-Agent: FreeField/<FreeField_Version> PHP/<PHP_Version>

Send Telegram message
^^^^^^^^^^^^^^^^^^^^^

This type of webhook is specific to sending messages to groups in Telegram. It
is a wrapper around the `Post JSON`_ webhook request type and functions more or
less the same as Post JSON internally, but exists as a convenience for making
Telegram webhooks easier to manage. Please refer to :doc:`/webhooks/telegram`
for more information about how to set up this webhook type.

Common webhook targets
----------------------

This documentation offers examples for several common webhook targets, such as
Discord. You are recommended to refer to the pages below if you are setting up
webhooks for any of these services, as they explain webhook properties specific
to these services, to help you get the most out of your webhooks.

.. toctree::
   :maxdepth: 1

   discord
   telegram

Webhook properties
------------------

Once you have created your webhook, you have to specify additional properties to
configure and customize its functionality. The main component is the webhook
URL, but there are other properties as well, which are described below.

.. hint:: The "Send Telegram message" webhook type has additional properties
          which are not described in this section. These properties are
          described in detail on the :doc:`/webhooks/telegram` subpage.

Webhook URL
^^^^^^^^^^^

The webhook URL is, along with the `Payload`_, the most important property of a
webhook. The webhook URL is the location on the web that FreeField will post the
payload to when the hook is triggered. This is typically an ``http://`` or
``https://`` URL, but may in certain specific circumstances be other protocols,
such as ``tg://`` for Telegram webhooks.

.. hint:: When you create a webhook listener on an service, you will receive a
          URL that is associated with it. This URL should be put in the Webhook
          URL field on FreeField.

Language
^^^^^^^^

You can choose which language should be used for your webhooks. The language you
select will be used to localize various `Substitution tokens`_ in your payload.
The language you choose will only be applied to these tokens, and not strings of
text that you define directly in your payload.

.. _webhook-icon-set:

Icon set
^^^^^^^^

The icon set you choose for your webhook is the icon set that will be used to
generate icon URLs if you use any icon set image substitution tokens in your
payload. Substitution tokens are explained in greater detail in `Substitution
tokens`_, while implementation details specific to icon set image URLs can be
found in the :doc:`/webhooks/tokenref`.

Species icons
^^^^^^^^^^^^^

To make it easier to spot particular rewards in webhook messages, FreeField
configures webhooks to use species icons for webhooks when available by default.
If enabled, an image representing a particular Pokémon species will be used
instead of the default grass "encounter" icon when using reward icon
substitution tokens, if the species rewarded by the research task is unambiguous
and known. If the encounter species is ambiguous or unknown, the default
encounter icon will be used instead. You can select which species icon set you
want to use in the "Species icons" setting. This behavior can be disabled by
unchecking the "Show icon for Pokémon" checkbox.

Geofence
^^^^^^^^

FreeField webhook triggers support :doc:`/geofencing`. If you select a geofence
for your webhook, then the webhook will only be triggered if the Pokéstop that
field research was reported for is within the bounds of the selected geofence.
For information on defining geofences, please see :doc:`/geofencing`.

Payload
-------

The payload is, along with the `Webhook URL`_, the most important property of a
webhook. When a webhook is triggered, the payload is what is sent to the webhook
URL, and is the data that the receiving service will process to e.g. generate
alerts. The syntax of the payload depends on the webhook type you have chosen,
and the structure depends on the service whose webhooks you are targeting.

.. hint:: Refer to the documentation for `Common webhook targets`_, as well as
          the documentation available directly from your target webhook service,
          to learn how to properly structure your webhook's payload.

Presets
^^^^^^^

FreeField has payload presets for several popular webhook target services. You
get the option to select a payload preset in the dialog that appears when you
first create your webhook. Presets are pre-defined payloads that, if one if
chosen, will fill in the Payload field of your webhook with pre-written
contents. This can save you from having to configure your webhook's payload from
scratch according to the documentation for the service that you are setting up a
webhook for.

Not all presets work for all webhook service providers, so you should consult
the documentation on `Common webhook targets`_ for information on which presets
you can use for your target service.

.. caution:: If you wish to create your own presets, you should avoid storing
             them in the presets directory in FreeField. This directory is
             deleted and overwritten every time you update FreeField. If you
             have created a preset you feel others would find useful, you can
             submit an issue or pull request for it on the `issue tracker
             <https://github.com/bilde2910/FreeField/issues>`_ on GitHub.

.. _sub-token-overview:

Substitution tokens
^^^^^^^^^^^^^^^^^^^

FreeField is very flexible in how you can structure your payloads. Once you have
decided on a structure for your payload, you can use substitution tokens to
actually insert data from FreeField into it. Substitution tokens are tags which
are replaced with relevant values when the webhook is about to be triggered, and
will update the payload for each webhook call to be specific to the field
research that was reported.

To place a substitution token in your payload, enter the substitution token
where you want it to appear. For example, if you are using Discord webhooks, and
you want the title of the message sent from FreeField to contain the name of the
Pokéstop that field research was reported at, and the footer to contain
coordinates for the Pokéstop and the time at which the report was made, you
could place the ``<%POI%>``, ``<%COORDS%>`` and ``<%TIME%>`` substitution tokens
in your webhook's payload like this:

.. code-block:: json
   :linenos:
   :emphasize-lines: 3,5,7

   {
       "embeds": [{
           "title": "Field research reported at <%POI%>",
           "footer": {
               "text": "Pokéstop coordinates: <%COORDS%>"
           },
           "timestamp": "<%TIME(c)%>"
       }]
   }


A reference of all available substitution tokens is available in a separate
documentation page. Please refer to your desired article below.

.. toctree::
   :maxdepth: 2

   tokenref

.. tip:: A quick reference of the most common substitution tokens are available
         directly from the webhook configuration section in FreeField. You can
         access it by clicking on :guilabel:`Show help` in the Payload section
         of your webhook.

Task-based filtering
--------------------

By default, FreeField will trigger webhooks regardless of what kind of research
is reported. However, you can restrict them to only being triggered when a
particular combination of research objectives and rewards are reported. This is
done using objective and reward filtering.

You can filter by both objectives and rewards in the same webhook. The webhook
will only be triggered if both the objectives and rewards component of the
filter are passed.

To add a filter for a particular type of objective or reward, click the
:guilabel:`+` button next to the "Objectives" or "Rewards" sections underneath
the webhook payload. A popup will appear that lets you select the type of
objective or reward that you want to filter. Select one.

Most objectives and rewards have additional parameters, such as the quantity of
items awarded by a reward, or the species of Pokémon that one must catch to
complete an objective. When reporting research, it is mandatory to fill out all
of these parameters, but when you set up webhook filters, these parameters can
be omitted. If you wish to omit a parameter from the filter, simply uncheck the
checkbox next to the parameter on the dialog box.

If a parameter has been omitted in the filter, FreeField will skip checking the
reported research task against it when determining whether or not to trigger the
webhook. For example, consider the following two reward filters; "3 Rare Candy"
and "[n] Rare Candy." Both of these reward filters will be triggered by the
"Rare Candy" reward, but while the former will only trigger if exactly three
Rare Candy are awarded by a research task, the latter will be triggered
regardless of the quantity of rare candies rewarded. Hence, the former reward
filter will only pass if exactly three Rare Candies are rewarded, while the
latter will also pass if the reported quantity is e.g. 1 or 5.

When you are done adding an objective or reward filter, click :guilabel:`Done`.
The newly created filter will be added to the list of active filters for this
webhook. You can add as many filters as you want, edit them whenever you wish,
and delete them if you no longer want them.

Filtering modes
^^^^^^^^^^^^^^^

Webhooks have two task filtering modes - whitelisting and blacklisting. The
default setting is whitelisting for both objectives and rewards.

If you choose the whitelisting mode, the webhook will only be triggered if any
one of the given objective or reward filters match the reported research task.
If you choose the blacklisting mode, the webhook will only be triggered if
*none* of the filters match the reported research task.

You can switch between them using the selection boxes at the top of the
"Objectives" and "Rewards" filter sections of the webhook.
