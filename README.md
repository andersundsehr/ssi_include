# EXT:ssi_include

This Extension will help you to update your Menu's and other Partials faster if they are rendered the Same over all your Pages.  

It uses the SSI technique to include Partials without rendering at that moment.  
It Caches the files inside typo3temp/tx_ssiinclude/ so it will reused the same partial for every request.  
The Partials will be different for the site Configuration and the sys_langauge and the name you will give the Partial.  

## Requirements:

- TYPO3 >=10
- SSI enabled on Server
- SSI errors disabled (otherwise there will be an error in your Backend)

## Installation

``composer req andersundsehr/ssi-include``

### enable SSI in webserver

You need to enable SSI on your Webserver:

Tested only with nginx. Should work with apache,

in your fastcgi part of your config:
````nginx configuration
location ~ \.php$ {
  # add next 2 lines:
  ssi on; #this must be on
  ssi_silent_errors on; #this should be on

  fastcgi_split_path_info ^(.+\.php)(/.+)$;
  fastcgi_pass php;
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $request_filename;
  fastcgi_read_timeout 600;
}
````

If you use **staticfilecache**, you should use it ♥️   
you need to add the ssi config in there as well:
````nginx configuration
...

location @sfc {
  # add next 2 lines:
  ssi on; #this must be on
  ssi_silent_errors on; #this should be on

  ...

  charset utf-8;
  default_type text/html;
  try_files /typo3temp/tx_staticfilecache/https_${host}_443${uri}/index /typo3temp/tx_staticfilecache/${scheme}_${host}_${server_port}${uri}/index =405;
}

...
````

And now the fun part. You can replace any partial rendering with the ViewHelper ``s:renderInclude``.  
That Partial will only be rendered once every 5 minutes for the complete Site (Site Configuration Site (not Page)).  
The only differentiation will be done by **site config**, **language** and the provided **name**.  
Optionally, you can add **cacheLifeTime** to define the lifetime of the partial in seconds.
If you include want to render the same partial with diffrent arguments it will still be the same content if you have the same name.

#### before:

````html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
      data-namespace-typo3-fluid="true">

<f:section name="Main">
  <div class="something something">
    <f:render partial="Menus/MainMenu" arguments="{_all}"/>
  </div>
</f:section>
````

#### after:

````html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
      xmlns:s="http://typo3.org/ns/AUS/SsiInclude/ViewHelpers"
      data-namespace-typo3-fluid="true">

<f:section name="Main">
  <div class="something something">
    <s:renderInclude name="mainMenu" cacheLifeTime="900" partial="Menus/MainMenu" arguments="{_all}"/>
  </div>
</f:section>
````


### Using the LazyDataProcessor to increase the Performance even more.

#### before:
````typoscript
10 = FLUIDTEMPLATE
10 {
  #...
  100 = TYPO3\CMS\Frontend\DataProcessing\MenuProcessor
  100 {
    #... Menu Processor Config
  }
  200 = AUS\AusProject\DataProcessing\SpecialProcessor
  #...
}
````

#### after:
````typoscript
10 = FLUIDTEMPLATE
10 {
  #...
  100 = AUS\SsiInclude\DataProcessing\LazyDataProcessor
  100.proxiedProcessor = TYPO3\CMS\Frontend\DataProcessing\MenuProcessor
  100.proxiedProcessor {
    #... Menu Processor Config
  }

  200 = AUS\SsiInclude\DataProcessing\LazyDataProcessor
  200.proxiedProcessor = AUS\AusProject\DataProcessing\SpecialProcessor
  200.variables = specialVar
  # the LazyDataProcessor needs to know that variable name should be proxied.
  # So we need to tell him if it is not configured inside the proxiedProcessor.as setting. 

  #...
}
````


Now the Setup is done 😊   

# with ♥️ from anders und sehr GmbH

> If something did not work 😮  
> or you appreciate this Extension 🥰 let us know.

> We are hiring https://www.andersundsehr.com/karriere/
