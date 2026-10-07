<?php

namespace JothamLec\MarketingToolkit\Support;

/**
 * The addon's permissions, as roles store them: renaming one takes away what roles were given.
 */
final class Permissions
{
    public const string VIEW = 'view marketing toolkit';

    public const string REDIRECTS = 'manage marketing toolkit redirects';

    public const string REPORTS = 'run marketing toolkit reports';
}
