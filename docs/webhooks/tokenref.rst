Substitution token reference
============================

This is a list of all payload substitution tokens available and implemented in
FreeField. For information on implementing them into your payloads, please see
:ref:`sub-token-overview` on the main webhooks documentation page.

Pokéstop information
--------------------

These substitution tokens provide information about the Pokéstop on which field
research was reported.

``<%POI%>``
   The name of the Pokéstop.

``<%LAT%>``
   The latitude of the Pokéstop.

``<%LNG%>``
   The longitude of the Pokéstop.

``<%COORDS%>``
   A coordinate pair in decimal degrees format (e.g. "42.63445°N, 87.12012°E").
   You can specify the number of decimals in each coordinate by including it in
   parentheses directly after "COORDS". For example, ``<%COORDS(4)%>`` will
   return the string "42.6344°N, 87.1201°E."

Research task information
-------------------------

These substitution tokens provide information about the research task that was
reported.

``<%OBJECTIVE%>``
   The research objective, for example "Catch 5 Pokémon."

``<%REWARD%>``
   The research reward, for example "Pokémon encounter."

You can also include information on the context of the report:

``<%REPORTER%>``
   The nickname of the user who reported the research task.

``<%TIME(format)%>``
   The exact time the report was received by FreeField. You have to specify a
   time formatting string when using this token. Replace ``format`` with a valid
   `PHP date() string <https://secure.php.net/manual/en/function.date.php>`_.
   For example, ``<%TIME(Y-m-d H:i:s)%>`` would result in a timestamp like
   "2018-10-02 15:38:55," while ``<%TIME(c)%>`` would result in something like
   "2018-10-02T15:38:55+02:00." Please refer to the aforementioned PHP manual
   for more format examples.

Navigation links
----------------

``<%NAVURL%>``
   Inserts a link that users can click on to get turn-based navigation to the
   Pokéstop that research was reported on. By default, this uses the default
   navigation provider as configured in the "Map settings" sections of the
   administration pages in FreeField. You can specify that you wish use one
   particular navigation provider by passing it in parentheses directly after
   "NAVURL." For example, ``<%NAVURL(bing)%>`` will override the default
   provider for navigation links, and instead create a link for navigation on
   Bing Maps. Valid navigation providers are ``bing``, ``google``, ``here``,
   ``mapquest``, ``waze`` and ``yandex``.

Icon set images
---------------

Some services support displaying alerts with images and/or thumbnails (Discord
is a good example of such a service). For these services, FreeField supports
passing a URL that points to an image representing the reported research
objective or reward.

``<%OBJECTIVE_ICON(format,variant)%>``
   Returns a URL to an image representing the reported field research objective.

``<%REWARD_ICON(format,variant)%>``
   Returns a URL to an image representing the reported field research reward.

Both of these tokens require a format and a variant. The format is the kind of
image that should be returned - ``vector`` or ``raster`` - and which one you
should use depends on what you will be using it for. Vectors, if present, are
generally much clearer and scale better than raster (bitmap) images, but not all
services support vector graphics. Raster images have much better compatibility,
but they do not scale as well and will start looking pixelated or blurry if
scaled too high. If you specify the ``vector`` format, but the :ref:`webhook-icon-set` you
have chosen does not offer vector variants of its icons, ``raster`` will be used
as a fallback.

The icon tokens also require a variant. This is either ``light`` or ``dark`` -
you should choose the one that fits best with the context in which the icons are
to be displayed.

.. note:: Not all icon sets have separate light and dark icons. If you use an
          icon set that uses the same graphics for both light and dark icons,
          then the icons returned by these substitution tokens will be the same
          regardless of which variant you have chosen.

Localization tokens
-------------------

If the webhook triggers an alert in a chatroom in your community, you may want
the message to contain some phrases that describe the research that was just
reported. FreeField supports substitution of localization tokens for webhooks
for this purpose. This allows FreeField to use placeholder values for various
strings that are then localized to the correct language before the webhook is
triggered.

``<%I18N(token[,arg1[,arg2...]])%>``
   Replaced by the localized value of ``token``, passing the given ``arg1..n``
   arguments to the localization function. E.g.
   ``<%I18N(webhook.report_title)%>`` is replaced with "Field research
   reported!" Arguments can be other substitution tokens, e.g.
   ``<%I18N(webhook.reported_by,<%REPORTER%>)%>`` is resolved to "Reported by,"
   followed by the nickname of the user who reported field research.

For more information on what localization tokens and arguments are, please refer
to Internationalization in the developer documentation.

.. tip:: Support for substitution of localization tokens was created in order
         for FreeField to support multiple languages out of the box for
         webhooks, by avoiding hardcoding English strings in payload presets. If
         you want to place strings like "Reported by" in your own payload, you
         could simply write out those strings in your native language directly
         in the payload, without using localization tokens, as you most likely
         won't change the language of a webhook later. Even if you do, you could
         just manually replace the strings with matching strings in the other
         language. In most cases, this is less work overall than setting up your
         webhook payloads to be completely internationalized.
