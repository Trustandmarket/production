function filterPrice(price){
    let myFloat = 0;
    if(price.replace(/[^0-9\.{1,2}]+/g, "") != ''){
        myFloat = price.replace(/[^0-9\.{1,2}]+/g, "");
    }
    return myFloat;
}