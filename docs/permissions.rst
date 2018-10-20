Permissions and groups
======================

FreeField uses a permissions tier system to assign users various permissions.
Each permission tier is called a group, and each group is assigned a permission
level, which is an integer between 0 and 250 describing the access level of
users within that role to functionality in FreeField.

.. tip:: Each group can additionally be assigned a color code that is used to
         distinguish users of a particular group in the user list. This is
         described in greater detail in the `Group settings`_ section of this
         page.

Default groups
--------------

By default, FreeField comes with seven groups:

+-----------------------+--------------------+--------------+
| Group name            | Permission level   | Color        |
+=======================+====================+==============+
| Site host             |                250 | Green        |
+-----------------------+--------------------+--------------+
| Administrator         |                200 | Red          |
+-----------------------+--------------------+--------------+
| Moderator             |                160 | Purple       |
+-----------------------+--------------------+--------------+
| Pokéstop submitter    |                120 | Turquoise    |
+-----------------------+--------------------+--------------+
| Registered member     |                 80 | < none >     |
+-----------------------+--------------------+--------------+
| Read-only member      |                 40 | < none >     |
+-----------------------+--------------------+--------------+
| Anonymous visitor     |                  0 | < none >     |
+-----------------------+--------------------+--------------+

Members of each group are automatically granted the permissions of all groups
below their permission level in addition to the permissions specific to their
own group. The default groups are set up in such a way that they should be safe
for production use if set up properly according to the intentions below.

.. caution:: If a permission that is reserved to a high-level group is desired
             for a lower level group, then that group should be granted that
             specific permission rather than be merged with a higher permission
             level group. Granting users access to permissions they do not need
             can be dangerous, and the security and availability of your site
             may be put at risk if you do so.

Site host
^^^^^^^^^

The site host is the group with highest permissions in FreeField. As the name
implies, this group should be reserved only for the person who is hosting the
FreeField site. Site hosts have access to settings that can be very dangerous to
change, or can break FreeField if changed, such as database connection settings,
authentication provider setup and site updates.

.. danger:: If your community has several administrators, it is strongly
            recommended that only the administrator responsible for hosting
            FreeField has this role, and that the others are assigned to the
            Administrator group instead. Failure to do so presents a risk that
            your entire FreeField installation can be hijacked by a rogue
            administrator, in the worst case warranting a complete reinstall of
            FreeField. This is explained in greated detail under
            `The "Manage own group" permission`_ at the end of this document.

Administrator
^^^^^^^^^^^^^

Administrators have the second highest level of access to FreeField, and have
access to most functionality needed to run and manage FreeField at a
co-administrative level. Administrators have access to functionality such as
site appearance settings, permissions management, security settings,
:doc:`/geofencing` and webhooks. Only trusted community leaders should be
assigned to this group, as malicious users with this level of access will be
able to do significant damage to FreeField.

Moderator
^^^^^^^^^

Moderators have the third highest level of access to FreeField. The purpose of
this group is to allow privileged members of a community to manage other
members' access - i.e. they can approve and reject users awaiting approval (see
:ref:`manual-approval`), delete the accounts of registered users, and modify and
delete any Pokéstops.

They can also change the group memberships of other users, but only for users
who are below their own level of access.

Pokéstop submitter
^^^^^^^^^^^^^^^^^^

Pokéstop submitters have the same privileges as regular members, with the
notable exception that they are able to submit new Pokéstops to the map. This
group should be assigned to members who wish to contribute to the completeness
of the map.

Registered member
^^^^^^^^^^^^^^^^^

This is the default group for new members. The only privilege these members have
over non-registered users is that they are able to submit field research tasks.
They can also overwrite field research tasks submitted by others, but this
permission can be revoked if deemed necessary by administrators.

Read-only member
^^^^^^^^^^^^^^^^

Users can be assigned to this group if they have caused trouble or for some
other reason have to be denied the permission to submit field research tasks. By
default, this group is functionally the same as the Anonymous group in terms of
permissions and access, but it has been included in case administrators would
want to restrict viewing the map to members with accounts only. This way,
administrators would be able to grant access to the map for a user, without also
having to grant them access to submitting field research, if the map is set up
to generally require an account to view the map in the first place.

Anonymous visitor
^^^^^^^^^^^^^^^^^

This group is the default group that all visitors, no matter where they are, are
automatically assigned to. Users who have not registered for FreeField are
treated as if they were members of this group. If you set any permissions to
this level of access, anyone who visits your FreeField instance can perform the
functions associated with that permission without having to sign in. The
permission level of this group is always 0, and cannot be changed.

The only permission which is set to this group by default is "View map," to
allow anyone to view the map and any submitted research without having to
authenticate.

.. _group-settings:

Group settings
--------------

You can add, remove and manage groups as you wish on the "Groups" section of the
administration interface. The following options are available to change for each
group:

Group name
^^^^^^^^^^

Each group has a name that is used to refer to that group elsewhere in
FreeField. This name can be just a plain string, such as "Moderator," or it can
be an internationalization token which is automatically translated into the
language of the user who is browsing FreeField.

All of the default groups use internationalization tokens to ensure that the
names are readable in all supported languages, and not limited to one single
language for everyone. An internationalization token takes the form
``{i18n:token_id}`` where the token ID is a string representing the key for a
particular localizable string in the localization files. The latest localization
files can be `found on GitHub
<https://github.com/bilde2910/FreeField/tree/master/includes/i18n>`_. The token
IDs used by groups in FreeField all start with ``group.level.``.

.. note:: If you want to use a custom name for a group, you should replace the
          entire internationalization token with the string that you wish to
          use. You should not add the string to your local copy of the
          localization files, as these are overwritten every time FreeField is
          updated - make the required changes on the administration pages
          instead.

Permission level
^^^^^^^^^^^^^^^^

Each group is assigned a permission level that dictates which permissions the
group has. Each group is granted all permissions at and below their permission
level automatically.

Two groups cannot share the same permission level.

.. caution:: It is strongly recommended that you do not change the permission
             levels assigned to the default groups. This is because updates to
             FreeField that add new permissions will use the default permission
             levels as a reference when they are populated with defaults on your
             FreeField installation. E.g. if a new permission is added that is
             only meant to be accessible to administrators by default, the
             permission will be set at level 200 regardless of what value you
             may have chosen for the local Administrators group.

Color
^^^^^

Each group can also be assigned a color. This color is displayed in other
places on the administration pages, as well as in the users list, to more easily
distinguish those groups from others. A group can also be assigned the default
color.

To assign a color to a group, select a color from the color input box in the row
that corresponds to your group. If you wish to use the default color, uncheck
the checkbox next to the color box. The default color is #888888 (r=136, g=136,
b=136) when using the dark color theme, and #777777 (r=119, g=119, b=119) when
using the light theme.

Actions
^^^^^^^

The "Groups" section on the administration pages allows administrators to
perform actions on groups. Actions can be performed on several groups at once
through selecting an action for several groups in the list, which will then be
applied all at once when clicking on :guilabel:`Save settings`. The available
actions for groups are as follows:

Delete group
   This action will, if selected, delete the group from the groups database.
   There are several considerations you should consider when deleting groups.
   See `Adding and removing groups`_ for more information.

Adding and removing groups
--------------------------

In addition to the default groups that are pre-installed on FreeField, it is
possible to add additional groups for more granular control over individual
permissions. When adding a new group, you have to enter a name for the group,
a permission level, and an optional color to represent it.

The permission level should be chosen so that it falls between two other groups
in FreeField. For example, if you wish to add a new group between the
"Registered member" group (level 80) and "Pokéstop submitter" group (level 120),
you could assign the new group permission level 100. Note that it is not
possible for two groups to share the same permission level.

You can also delete groups by selecting the "Delete group" action for the group
in the groups list. There are several considerations you should consider when
deleting a group:

-  Users who are in the group when it is deleted will automatically be
   reassigned to an "Unknown" group with a permission level corresponding to the
   level of the deleted group.
-  Permissions which are set to the group that is being deleted will
   automatically change to be granted to the aforementioned "Unknown" group.
   This ensures that the permissions of any members in the group remain
   unchanged.
-  Users can be moved from the "Unknown" group to any other group, but cannot be
   moved from another group to the "Unknown" group.
-  Similarly, permissions which are set to the permission level of the "Unknown"
   group can be changed to another permission level, but cannot be changed back
   again.
-  If a new group is created with the same permission level as a previously
   deleted group, then all members who are currently in the "Unknown" group
   corresponding to the permission level of that group are automatically moved
   to the new group.
-  This also applies to permissions - any permissions which are explicitly
   granted to any "Unknown" group that corresponds to the level that the new
   group is added at, are reconfigured to be granted to the newly added group
   instead.
-  If you change the permission level of, or delete, a default group, then any
   future updates to FreeField that add additional permissions to that default
   group will result in those new permissions automatically being assigned to an
   "Unknown" group that correspnds to the default level of that group. You may
   want to change the group assignment of those permissions after such an update
   has completed.

Default group for new members
-----------------------------

The default group for new members is "Registered member." This can be changed on
the "Permissions" section of the administration pages.

.. hint:: If you wish to manually approve new members before granting them
          access to FreeField, then this is not the setting you should change.
          Instead, look into :ref:`manual-approval`.

Managing permissions
--------------------

You can find a list of all configurable permissions in FreeField on the
"Permissions" section of the administration pages. If you set a permission to a
particular level, then all users who are assigned to a group with a permission
level at or above the level of the selected group are granted the permission in
question.

Users who have access to change permissions (i.e. users who have been granted
"Manage permissions") are only able to change permissions whose currently
assigned group has a permission level lower than the one they themselves are a
member of. This means that Administrators, for example, cannot change
permissions which are currently granted to Administrators or the Site host.
Neither can they restrict a permission that they *can* change to a group with a
permission level that is the same as or higher than that of their own group.
This means that Administrator users cannot change the assigned group of a
permission that is currently granted to Pokéstop submitters, to Administrators
or the Site host. They can, however, change the permission to any group ranging
from Anonymous visitor through Moderator, as these are all below the permission
level of the Administrator user who is making those changes.

The "Manage own group" permission
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

There is one permission in FreeField that warrants extra attention in the
documentation - the "Manage own group" permission, which by default is only
granted to the Site host.

The default behavior of FreeField when it comes to users sharing a group, is
that users can only make changes to other users, groups and permissions that are
*below* the current level of their own group. This means that members within a
group cannot change each others' details, they cannot restrict access to a
permission to their own group, and they cannot assign or revoke access for
members to their own group. In practice, this means that moderators cannot
appoint other moderators, and administrators cannot appoint other administrators
- they would have to consult with a user of a higher level group to make those
changes on their own behalf.

This is a security measure. If e.g. administrators were able to manage their own
group, then nothing would stop one administrator from demoting all other
administrators to a lower rank, taking practically full control over FreeField
and leaving the Site host to clean up the mess. Furthermore, restricting access
for users to manage their own group and their group's members reduces the attack
surface for malicious users who try to seize control of an administrator account
for e.g. escalating their own account to administrator level to only one account
(the Site host) rather than the entire administration team.

This unfortunately has a significant practical implication - several settings in
FreeField are restricted to being changeable by the Site host only by default,
meaning that if the Site host could not change settings at their own level, they
would not be able to change the settings despite being super-administrators on
the site, a permission level whose intention is to be able to manage literally
every setting in FreeField.

To remedy this, the "Manage own group" setting exists. Groups who have this
permission will bypass the group self-management restrictions, so that they
*can* make changes at their own permission level. This setting essentially
raises the permission level of the groups who have the permission granted by
one. This is also why the "Manage own group" setting should always remain at the
Site host level and should never be granted to other users.

Members of groups with this permission granted will still not be able to change
permissions or group/membership settings for any groups *above* their current
permission level, even though they can make changes *at or below* their own
level.

This permission is also the reason that there should only be one Site host. If
you as the Site host assign another user to the Site host group, that user would
have full rights to revoke your own Site host group membership, seizing full and
unrestricted access to the entire FreeField installation, and eliminating your
own ability to take back control. The only way to recover from such a breach
would be to access the users table in the database and change the malicious
user's permission level directly. If the user manages to switch the database
connection settings to another database provider first, then recovering would be
even harder, likely warranting directly modifying the FreeField config.json file
or even completely reinstalling FreeField.
