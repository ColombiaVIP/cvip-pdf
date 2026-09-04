# ent

Encode and decode HTML entities

[![build status](https://secure.travis-ci.org/ljharb/ent.png)](http://travis-ci.org/ljharb/ent)

# example

``` js
var ent = require('ent');
console.log(ent.encode('<span>©moo</span>'))
console.log(ent.decode('&pi; &amp; &rho;'));
```

```
&lt;span&gt;&copy;moo&lt;/span&gt;
π & ρ
```

![ent](https://web.archive.org/web/20170823120015if_/http://substack.net/images/ent.png)

# methods

## encode(str)

Escape unsafe characters in `str` with html entities.

## decode(str)

Convert html entities in `str` back to raw text.

# credits

HTML entity tables shamelessly lifted from perl's
[HTML::Entities](http://cpansearch.perl.org/src/GAAS/HTML-Parser-3.68/lib/HTML/Entities.pm)

# install

With [npm](https://npmjs.org) do:

```
npm install ent
```

# license

MIT
