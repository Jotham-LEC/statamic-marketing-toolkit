<?php

namespace JothamLec\MarketingToolkit\Support;

/**
 * Lists the addon's permissions as roles store them, so renaming one takes away what roles were given.
 */
final class Permissions
{
    public const string VIEW = 'view marketing toolkit';

    public const string REDIRECTS = 'manage marketing toolkit redirects';

    public const string REPORTS = 'run marketing toolkit reports';
}
