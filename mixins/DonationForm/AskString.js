function convertAsk(amount, currency, country) {
    // given an amount in USD, find an "equivalent" amount for the given currency and country

    var usdamount = parseInt(amount, 10);
    if(isNaN(usdamount)){
        return 0;
    }
 
    var usdbase = [3, 5, 10, 20, 30, 50, 100];
 
    if(currency === 'USD'){
        if(country === 'US'){
            return usdamount;
        }
    }
 
    var other = {
        'EUR' : {
            'default' : [3, 5, 10, 20, 30, 50, 100]
        },
        'GBP' : [3, 5, 10, 20, 30, 50, 100],
        'CAD' : [3, 5, 10, 20, 30, 50, 100],
        'AUD' : [3, 5, 10, 20, 30, 50, 100],
        'NZD' : [3, 5, 10, 20, 30, 50, 100],
        
        'INR' : [100, 200, 300, 500, 1000, 2000, 3000],
        'JPY' : [1000, 1500, 2000, 3000, 5000, 10000, 15000],
        'RUB' : [100, 150, 200, 500, 1000, 2000, 3000],
        'BRL' : [10, 20, 30, 50, 100, 250, 500],
        'SEK' : [50, 100, 150, 250, 500, 750, 1000],
        'NOK' : [50, 100, 150, 250, 500, 750, 1000],
        'ILS' : [25, 50, 100, 200, 300, 500, 1000],
        'DKK' : [50, 100, 150, 200, 300, 500, 750],
        'SGD' : [5, 20, 50, 75, 100, 150, 300],
        'HKD' : [50, 200, 300, 500, 1000, 1500, 2000],
        'TWD' : [150, 300, 500, 1000, 1500, 2000, 5000],
        'CNY' : [50, 75, 100, 300, 500, 1000, 1500],
        'PLN' : [20, 35, 50, 100, 150, 300],
        'CZK' : [100, 150, 300, 1000, 1500, 3000,],
        'ZAR' : [25, 50, 100, 300, 500, 750, 1000],
        'KRW' : [5000, 10000, 20000, 30000, 50000, 75000, 100000],
        'TRY' : [10, 25, 50, 100, 150, 200, 500],
        'SAR' : [50, 100, 200, 500, 750, 1000, 1500],
        'THB' : [100, 250, 500, 750, 1000, 2000, 3000],
        'HUF' : [1000, 2000, 5000, 10000, 20000, 50000, 100000],
        'IDR' : [50000, 75000, 100000, 150000, 200000, 500000, 1000000],
        'ARS' : [20, 50, 100, 200, 500, 750, 1000],
        'PHP' : [200, 500, 750, 1000, 2000, 3000, 5000],
        'CLP' : [2500, 5000, 10000, 20000, 30000],
        'MXN' : [50, 100, 200, 500, 750, 1000, 2000],
        'UYU' : [100, 200, 400, 1000, 1500, 1950, 5000],
        'COP' : [5000, 10000, 20000, 50000, 100000, 150000, 200000],
        'NIO' : [100, 250, 500, 1000, 1500, 2500, 5500],
        'DOP' : [200, 500, 1000, 2000, 5000, 7500, 10000],
        'UAH' : [50, 75, 150, 300, 500, 750, 1000],
        'MYR' : [20, 30, 50, 100, 200, 300, 500],
        'RON' : [25, 50, 75, 100, 200, 300, 500],
        'BGN' : [10, 25, 50, 75, 100, 150, 200],
        'HRK' : [35, 50, 100, 250, 500, 1000, 1500],
        'QAR' : [20, 50, 75, 185, 250, 350, 1000],
        'KWD' : [2, 5, 10,15, 25, 30, 75],
        'LTL' : [15, 25, 50, 100, 200, 250, 600],
        'KZT' : [750, 1500, 3000, 7500, 12000, 15000, 35000],
        'LVL' : [5, 10, 20, 30, 40, 50, 75],
        'CRC' : [2500, 5000, 10000, 20000, 50000, 75000, 100000],
        'VEF' : [5, 10, 20, 50, 75, 100, 250],
        'BHD' : [4, 5, 10, 25, 50, 100],
        'PEN' : [15, 30, 50, 150, 200, 275, 700],
        // the following USD ask strings are for countries in which we fundraise
        // in USD, but that need a difference ask string than in the US
        'USD' : {
            'default' : usdbase
        }
    };
 
 
    if (other[currency] == null) {
        return usdamount;
    }
    var index = $.inArray(usdamount, usdbase);
    if (index == -1) {
        // the amount is not in the USD ask array, find a near neighbor
        index = 0;
        while (usdbase[index+1] < usdamount && index < usdbase.length + 1) {
            index++;
        }
    }
 
    if ( other[currency] instanceof Array ) { // simplest case, just one array for this currency
        return other[currency][index];
    } else { // arrays for multiple countries
        if (other[currency][country] != null) {
            return other[currency][country][index];
        } else {
            return other[currency]['default'][index];
        }
    }
    
};

function getAverage(currency, country, language) {
    // return a string of the "average" donation amount in the given currency & country for use in the banner text
     var usdaverage = 30;

     var average = {
        'EUR' : {
            'default' : 30
        },
        'GBP' : 20,
        'CAD' : 30,
        'AUD' : 30,
        'NZD' : 30,

        'INR' : 1500,
        'JPY' : 3000,
        'RUB' : 1000,
        'BRL' : 60,
        'SEK' : 200,
        'NOK' : 150,
        'ILS' : 100,
        'DKK' : 150,
        'SGD' : 30,
        'HKD' : 200,
        'TWD' : 1000,
        'CNY' : 200,
        'PLN' : 100,
        'CZK' : 500,
        'ZAR' : 300,
        'KRW' : 30000,
        'TRY' : 50,
        'SAR' : 100,
        'THB' : 1000,
        'HUF' : 5000,
        'IDR' : 300000,
        'ARS' : 150,
        'ISK' : 4000,
        'PHP' : 1500,
        'CLP' : 20000,
        'MXN' : 300,
        'UYU' : 750,
        'COP' : 30000,
        'NIO' : 750,
        'DOP' : 1500,
        'UAH' : 300,
        'MYR' : 150,
        'RON' : 200,
        'BGN' : 75,
        'HRK' : 250,
        'QAR' : 185,
        'KWD' : 15,
        'LTL' : 100,
        'KZT' : 5000,
        'LVL' : 30,
        'CRC' : 20000,
        'VEF' : 50,
        'BHD' : 25,
        'PEN' : 100,
       'USD' : {
            'default' : usdaverage
        }
    };

    if (average[currency] == null) {
        return currencyLocalize('USD', usdaverage, language); // fallback to $30 if not defined
    }

    if ( typeof(average[currency]) === 'number' ) { // simplest case, single average for currency
        return currencyLocalize(currency, average[currency], language);
    } else { // different averages for different countries
        if (average[currency][country] != null) {
            return currencyLocalize(currency, average[currency][country], language);
        } else {
            return currencyLocalize(currency, average[currency]['default'], language);
        }
    }

};

function getMinimumString(currency, country, language) {
    // return a string of the "minimum" donation amount in the given currency & country for use in the banner text
    var usdminimum = 3;

    var minimum = {
        'EUR' : {
            'default' : 3
        },
        'GBP' : 3,
        'CAD' : 3, 
        'AUD' : 3, 
        'NZD' : 3, 
        
        'INR' : 100,
        'JPY' : 1000, 
        'RUB' : 100, 
        'BRL' : 10, 
        'SEK' : 50, 
        'NOK' : 50, 
        'ILS' : 25, 
        'DKK' : 50,
        'SGD' : 5, 
        'HKD' : 50, 
        'TWD' : 150,
        'CNY' : 50, 
        'PLN' : 20,
        'CZK' : 100,
        'ZAR' : 25,
        'KRW' : 5000,
        'TRY' : 10,
        'SAR' : 50,
        'THB' : 100,
        'HUF' : 1000,
        'IDR' : 50000,
        'ARS' : 20,
        'PHP' : 200,
        'CLP' : 2500,
        'MXN' : 50,
        'UYU' : 100,
        'COP' : 5000,
        'NIO' : 100,
        'DIO' : 200,
        'UAH' : 50,
        'MYR' : 20,
        'RON' : 25,
        'BGN' : 10,
        'HRK' : 25,
        'QAR' : 20,
        'KWD' : 1,
        'LTL' : 15,
        'KZT' : 750,
        'LVL' : 5,
        'CRC' : 2500,
        'VEF' : 5,
        'BHD' : 4,
        'PEN' : 15,
       'USD' : {
            'default' : usdminimum
        }
    };

    if (minimum[currency] == null) {
        return currencyLocalize('USD', usdminimum, language); // fallback to $30 if not defined
    }

    if ( typeof(minimum[currency]) === 'number' ) { // simplest case, single minimum for currency
        return currencyLocalize(currency, minimum[currency], language);
    } else { // different minimums for different countries
        if (minimum[currency][country] != null) {
            return currencyLocalize(currency, minimum[currency][country], language);
        } else {
            return currencyLocalize(currency, minimum[currency]['default'], language);
        }
    }

}
