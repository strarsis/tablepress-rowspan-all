# tablepress-rowspan-all
TablePress Extension: Rowspans everywhere

## A note about caching
TablePress plugin caches the table markup (as transients).
If the table markup doesn't change after installing/updating this plugin,
the TablePress cache may has to be invalidated.
Either delete the TablePress-related transients,
all transients (as transients should only be used for temporary data),
or update the cache by re-saving the tables that should use 
this plugin by using the `outside_rowspan` trigger word.
