Authentication
==============

FreeField uses third party OAuth2 providers for authenticating users. This
allows flexibility with letting users log in to FreeField using an existing
account on the same service that your community uses for communication, and also
eliminates inherent problems associated with storing emails and passwords in a
database.

You are required to set up at least one authentication provider in FreeField.
However, you are free to set up several providers if you want to offer greater
flexibility for users, or if your community uses several social media platforms.
Instructions for setting up each supported authentication provider is provided
on the pages below.

.. toctree::
   :maxdepth: 1
   :caption: Available authentication methods:

   discord
   telegram
   reddit
   groupme
