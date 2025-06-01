/*     function displayCounter() {
        // check if the localStorage object is supported by the browser
        if ('localStorage' in window && window['localStorage'] !== null) {
			//alert("ok");
            // if the counter has been defined, increment its value, // otherwise, set it to 0
            ('counter' in localStorage && localStorage['counter'] !== null && localStorage['counter'] !== 0) ? localStorage['counter']++ : localStorage['counter'] = 0;
            
            return localStorage['counter'];
        }
    }
	if(displayCounter() == 0){
        //Test locale to make redirection
        //Make redirection for locale
        var lang = "{{app.request.locale}}";
        var url = window.location.href;
        //Ajax request

        //End Ajax
        //console.log(url);
    }
    
    window.addEventListener("unload", () => localStorage.removeItem('counter')); */