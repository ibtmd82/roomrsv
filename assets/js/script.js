/*------------------------------------------------------------------
* Bootstrap Simple Admin Template
* Version: 3.0
* Author: Alexis Luna
* Website: https://github.com/alexis-luna/bootstrap-simple-admin-template
-------------------------------------------------------------------*/
(function() {
    'use strict';
    if (localStorage.getItem("clientid") === null)
        window.location.href = "login.html";
    else {
        let $elm = $('div a').find('span[data-key="sclientid"]');
        //console.log($inputelm.attr('name'));
        console.log($elm.text());
        $elm.text(localStorage.getItem("clientid"));
    }

    //$.support.cors = true;
    //$('.datepicker-here').val(Date());
   /*$('.datepicker-here').datepicker({
        "setDate": new Date(),
        //"autoclose": true
    });*/

    $.fn.delItem = function(msgid){
        //alert(msgid);
        var data = JSON.parse(localStorage.getItem("msgplays"));
        var index = data.data.findIndex(item => item[0] === msgid);
        //alert(index);
        delete data.data.splice(index,1);
        localStorage.setItem("msgplays",JSON.stringify(data));
    }

    $.fn.delSrvItem = function(msgid){
        alert(msgid);
        fetch(`http://8.219.159.214:8080/Account/DelMsg?msgid=${msgid}`, {
            headers: {
                'clientid': localStorage.getItem("clientid")
            }
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                setTimeout(function(){ location.reload(); }, 2000);
                // //data2 = JSON.stringify(data);
                // //data = JSON.parse(data1);
                // summary_short_fields.forEach(item => {
                //     let $tr = $('table tbody').find(`td[data-key="${item}"]`);
                //     var index = summary_short_fields.findIndex(key => key === item);
                //     //console.log(data);
                //     $tr.text(data[index]);
            });
        //return false;
    }

    $.fn.delSrvCusItem = function(cusid){
        alert(cusid);
        fetch(`http://8.219.159.214:8080/Account/DelCus?cusid=${cusid}`, {
            headers: {
                'clientid': localStorage.getItem("clientid")
            }
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                //
                setTimeout(function(){ location.reload(); }, 2000);
                // //data2 = JSON.stringify(data);
                // //data = JSON.parse(data1);
                // summary_short_fields.forEach(item => {
                //     let $tr = $('table tbody').find(`td[data-key="${item}"]`);
                //     var index = summary_short_fields.findIndex(key => key === item);
                //     //console.log(data);
                //     $tr.text(data[index]);
            });
        //
        //return false;
    }

    // Toggle sidebar on Menu button click (supports dynamically-rendered navbar)
    const toggleSidebar = function(event) {
        if (event) {
            event.preventDefault();
        }
        $('#sidebar').toggleClass('active');
        $('#body').toggleClass('active');
    };
    window.toggleSidebarLayout = toggleSidebar;
    $(document).on('click', '#sidebarCollapse', toggleSidebar);
    $(document).on('touchend', '#sidebarCollapse', toggleSidebar);

    // $('#savemsg').on('click', function() {
    //     var data = JSON.parse(localStorage.getItem("msgplays"));
    //     data.push({'number':'2','phone':'0966093512','message':'hung.2dai34dd5n','status':'active','actions':''});
    //     localStorage.setItem("msgplays",JSON.stringify(data));
    //     //$('#areturn').click();
    //     location.replace("msgplays.html");
    //     //alert(location.href);
    //     //location.reload();
    //     //redirect_self("msgplays.html");
    // });
    $('#readandroid').on('click', function(event) {
        event.preventDefault();
        localStorage.clear();
        window.location.href = "login.html";
    });

    $('#write').on('click', function (event) {
        //alert('write');
        fetch('http://8.219.159.214:8080/Account/TestPost', {
        //fetch('http://localhost:8511/Account/TestPost', {
            method: 'POST',
            body: localStorage.getItem("msgplays"),
            //body: {sdate:localStorage.getItem("ldate"), msgplays:localStorage.getItem("msgplays")},
            headers: {
                'Content-type': 'application/json; charset=UTF-8',
                'Accept':'application/json',
                'clientid': localStorage.getItem("clientid")
                 //'Access-Control-Allow-Origin':'null',
                 //'Access-Control-Allow-Credentials':'true',
            }
        })
        .then(res => res.json())
        .then(data => { 
            //console.log('hello' + JSON.stringify(data));
            //localStorage.clear();
            localStorage.setItem("msgplays",JSON.stringify(data));
            location.reload();
        })
        .catch(error => {
            //handle error
            alert(error);
            //console.log(error)
        });
        event.preventDefault();
        event.stopPropagation();
        localStorage.setItem("msgplays",JSON.stringify({'data':[]}));
        //return false;
    });
    $('#getresult').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        fetch('http://8.219.159.214:8080/Account/TestResult', {
            headers: {
                'clientid': localStorage.getItem("clientid")
            }
        })
        .then(res => res.text())
        .then(data => {alert(data);})
        
    });
    // Auto-hide sidebar on window resize if window size is small
    // $(window).on('resize', function () {
    //     if ($(window).width() <= 768) {
    //         $('#sidebar, #body').addClass('active');
    //     }
    // });
})();

