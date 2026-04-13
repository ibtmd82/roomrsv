// Initiate datatables in roles, tables, users page
(function() {
    'use strict';
    
    if (localStorage.getItem("msgplays") === null) {
        var data = {'data':[]};
        localStorage.setItem("msgplays",JSON.stringify(data));
    }
    /*else {
        var data = JSON.parse(localStorage.getItem("msgplays"));
    }*/
    
    
    //localStorage.setItem("msgplays",JSON.stringify({'data':[]}));

    $('#dataTables-example').DataTable({
        responsive: true,
        // responsive: {
        //     details: {
        //         renderer: function (api, rowIdx, columns) {
        //             // let data = columns.map((col, i) => {
        //             //     return col.hidden
        //             //         ? '<tr data-dt-row="' +
        //             //                 col.rowIndex +
        //             //                 '" data-dt-column="' +
        //             //                 col.columnIndex +
        //             //                 '">' +
        //             //                 '<td>' +
        //             //                 col.title +
        //             //                 ':' +
        //             //                 '</td> ' +
        //             //                 '<td>' +
        //             //                 col.data +
        //             //                 '</td>' +
        //             //                 '</tr>'
        //             //         : '';
        //             // }).join('');

        //             let data = '<tr><td>hello</td></tr>';
     
        //             let table = document.createElement('table');
        //             table.innerHTML = data;
     
        //             return data ? table : false;
        //         }
        //     }
        // },
        paging:false,
        scrollCollapse: false,
        //scrollY:'200px',
        //pageLength: 20,
        lengthChange: false,
        searching: false,
        ordering: true,
        
        "ajax": function (data, callback, settings) {
            callback(
              JSON.parse( localStorage.getItem('msgplays') )
            );
        },
        serverSide:true,
        columns: [
            {
                title: 'STT',
                //render:DataTable.render.number()
            },
            {
                title: 'Khách',
                render: function (data, type) {
                    /*if (type === 'display') {
                        let link = 'http://datatables.net';
     
                        if (data[0] < 'H') {
                            link = 'http://cloudtables.com';
                        }
                        else if (data[0] < 'S') {
                            link = 'http://editor.datatables.net';
                        }
     
                        return '<a href="' + link + '">' + data + '</a>';
                    }*/
     
                    return data;
                }
            },
            {
                title: 'Tin',
                render:function (data, type, row, meta) {
                    if (type === 'display') {
                        let color = 'blue';//console.log(row);
     
                        if (row[3] == 'error') {
                            color = 'red';
                        }
                        //
                        return `<span style="color:${color}">${data}</span>`;
                        //return '<a href="' + link + '">' + data + '</a>';
                    }
     
                    return data;
                }
            },
            {
                title: 'Trạng thái',
                //new, edit, error, success
            },
            {
                //title: 'actions',
                classname:'text-end',
                render: function (data, type, row) {
                    //var that = this;
                    return `<a href="formmsg.html?msgid=${row[0]}&msg=${row[2]}&status=${row[3]}&errmsg=${row[4]}&start=${row[5]}&len=${row[6]}" class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></a>` +
                        `<span>&nbsp</span><a href="" onclick="$.fn.delItem('${row[0]}');" class="btn btn-outline-danger btn-rounded"><i class="fas fa-trash"></i></a>`;
                }
            },
            {
                defaultContent:"",
                visible:false,
            },
            {
                defaultContent:"",
                visible:false,
            }],
        //data: data
    });

    var table_msgplayed = $('#dataTables-msgplayed').DataTable({
        responsive: true,
        paging:false,
        scrollCollapse: false,
        //scrollY:'200px',
        //pageLength: 20,
        lengthChange: false,
        searching: false,
        ordering: true,
        "ajax": {
            "url": "http://8.219.159.214:8080/Account/TestGetMsgs",
            //"url": "http://localhost:8511/Account/TestGetMsgs",
            //"contentType": "application/json",
            "type": "POST",
            //"data": {sdate: localStorage.getItem("aaa")},
            "data": function(d) {
                console.log(localStorage.getItem("ldate"));
                if (localStorage.getItem("ldate") !== null)
                    d.sdate = localStorage.getItem("ldate");
                else d.sdate = $('.datepicker-here').val();
                //d.clientid='huy';
            },
            "beforeSend": function(request) {
                request.setRequestHeader("Accept", "application/json");
                request.setRequestHeader("clientid", localStorage.getItem("clientid"));
            },
            // "success": function(result){
            //     //alert(result);
            //     console.log('my message' + JSON.stringify(result));
            // }
        },
        //defaultContent:`<a href='' onclick='alert("df");'></a>`,
        serverSide:true,
        columns: [
            {
                title: 'id',
                //render:DataTable.render.number(),
            },
            {
                title: 'Khách',          
            },
            {
                title: 'Nội dung',
                
            },
            {
                title: 'Tổng',
                //className: 'dt-control',
                defaultContent: '',
                render: function (data, type, row) {
                    //var that = this;
                    return `<span>&nbsp</span><a href="" onclick="$.fn.getMsgAmount('${row[0]}', event);" class="btn btn-outline-info btn-rounded"><i class="fas fa-eye"></i></a>`;
                }
            },
            {
                //title: 'actions',
                //"data": null,
                defaultContent: "",
                classname:'text-end',
                render: function (data, type, row) {
                    //var that = this;
                    return `<span>&nbsp</span><a href="" onclick="$.fn.delSrvItem('${row[0]}');return false;" class="btn btn-outline-danger btn-rounded"><i class="fas fa-trash"></i></a>`;
                }
            },
        ],
        //data: data
    });

    function format(rowd, resultd) {
        // `d` is the original data object for the row
        return (
            '<ul class="dtr-details">' +
            '<li>' +
            '<span class="dtr-title">Nội dung</span>' +
            '<span class"dtr-data">' + rowd[2] + '</span>' +
            '</li>' +
            '<li>' +
            '<span class="dtr-title">Tổng</span>' +
            '<span class"dtr-data">' + resultd[4] + '</span>' +
            '</li>' +
            '<li>' +
            '<span class="dtr-title">Tổng 2C</span>' +
            '<span class"dtr-data">' + resultd[2] + '</span>' +
            '</li>' +
            '<li>' +
            '<span class="dtr-title">Tổng 3C</span>' +
            '<span class"dtr-data">' + resultd[3] + '</span>' +
            '</li>' +
            '</ul>'
        );
    }

    $.fn.getMsgAmount = function(msgid, e)
    {
        //alert(msgid);
        e.preventDefault();
        e.stopPropagation();
        fetch(`http://8.219.159.214:8080/Account/GetMsgPlayAmount?msgid=${msgid}`, {
            headers: {
                'clientid': localStorage.getItem("clientid")
            }
        })
            .then(res => res.json())
            .then(data => {
                //alert(data);
                console.log(data);
                //alert(data.result[4]);
                //let tr = e.target.closest('tr');
                
                var tr = $(e.target.closest('tr'));
                if(tr.hasClass('child')){
                    tr = tr.prev();
                }
                //var data = table.row(tr).data();
                let row = table_msgplayed.row(tr);
                
                //alert(JSON.stringify(row));
                //let td = e.target.closest('td');
                //let cell = table_msgplayed.cell(td);
                console.log(row.data());
                //
                //table_msgplayed.row(0).data(datat).draw(true);
                //td.removeClass("");
                row.child(format(row.data(), data.result),['child']).show();
                // if (row.child.isShown()) {
                //     // This row is already open - close it
                //     row.child.hide();
                // }
                // else {
                //     // Open this row
                //     row.child(format(row.data())).show();
                // }
                //setTimeout(function(){ location.reload(); }, 2000);
                // //data2 = JSON.stringify(data);
                // //data = JSON.parse(data1);
                // summary_short_fields.forEach(item => {
                //     let $tr = $('table tbody').find(`td[data-key="${item}"]`);
                //     var index = summary_short_fields.findIndex(key => key === item);
                //     //console.log(data);
                //     $tr.text(data[index]);
            });
            return false;
    };

    $('#dataTables-cuss').DataTable({
        responsive: true,
        paging:false,
        scrollCollapse: false,
        //scrollY:'200px',
        //pageLength: 20,
        lengthChange: false,
        searching: false,
        ordering: true,
        //stateSave: true,
        //stateSaveCallback: function(settings,data) {
        //    localStorage.setItem( 'DataTables_' + settings.sInstance, JSON.stringify(data))
        //},
        "ajax": {
            "url": "http://8.219.159.214:8080/Account/GetCusList",
            //"url": "http://localhost:8511/Account/TestGetMsgs",
            //"contentType": "application/json",
            //dataSrc: '',
            "type": "POST",
            //"data": {sdate: localStorage.getItem("aaa")},
            "data": function(d) {
                //console.log(localStorage.getItem("ldate"));
                //if (localStorage.getItem("ldate") !== null)
                //    d.sdate = localStorage.getItem("ldate");
                //else d.sdate = $('.datepicker-here').val();
                //d.clientid='huy';
            },
            "beforeSend": function(request) {
                request.setRequestHeader("Accept", "application/json");
                request.setRequestHeader("clientid", localStorage.getItem("clientid"));
            },
            // "success": function(result){
            //     //alert(result);
            //     console.log('my message' + JSON.stringify(result));
            // }
        },
        serverSide:true,
        columns: [
            {
                title: 'Mã KH',
                data: 'Code',
            },
            {
                title: 'Tên KH',
                data: "FullName"
            },
            // {
            //     title: 'status',
            //     //success
            // },
            {
                //title: 'actions',
                data: "ID",
                classname:'text-end',
                render: function (data, type) {
                    //var that = this;
                    return `<a href="formcus.html?cusid=${JSON.stringify(data)}" class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></a>` +
                        `<span>&nbsp</span><a href="" onclick="$.fn.delSrvCusItem('${data}');return false;" class="btn btn-outline-danger btn-rounded"><i class="fas fa-trash"></i></a>`;
                }
            },
        ],
        //data: data
    });

    $('#dataTables-cusssum').DataTable({
        responsive: true,
        paging:false,
        scrollCollapse: false,
        //scrollY:'200px',
        //pageLength: 20,
        lengthChange: false,
        searching: false,
        ordering: true,
        "ajax": {
            "url": "http://8.219.159.214:8080/Account/TestGetCussSum",
            //"url": "http://localhost:8511/Account/TestGetMsgs",
            //"contentType": "application/json",
            "type": "POST",
            //"data": {sdate: localStorage.getItem("aaa")},
            "data": function(d) {
                //console.log(JSON.stringify(d));
                //
            },
            "beforeSend": function(request) {
                request.setRequestHeader("Accept", "application/json");
                request.setRequestHeader("clientid", localStorage.getItem("clientid"));
            },
        },
        serverSide:true,
        columns: [
            {
                title: 'Khách',
                render: function (data, type, row, meta) {
                    /*if (type === 'display') {
                        let link = 'http://datatables.net';
     
                        if (data[0] < 'H') {
                            link = 'http://cloudtables.com';
                        }
                        else if (data[0] < 'S') {
                            link = 'http://editor.datatables.net';
                        }
     
                        return '<a href="' + link + '">' + data + '</a>';
                    }*/
     
                    return row[0];
                }
            },
            {
                title: 'MN',
                render:function (data, type, row, meta) {
                    if (type === 'display') {
                        let color = 'blue';//console.log(row);
     
                        if (parseInt(row[2]) < 0) {
                            color = 'red';
                        }
                        //
                        //return `<span style="color:${color}">${data}</span>`;
                        return `<span style="color:${color}">${row[2]}</span>`;
                        //return '<a href="' + link + '">' + data + '</a>';
                    }
     
                    return data;
                }
            },
            {
                title: 'MT',
                render:function (data, type, row, meta) {
                    if (type === 'display') {
                        let color = 'blue';//console.log(row);
     
                        if (parseInt(row[3]) < 0) {
                            color = 'red';
                        }
                        //
                        //return `<span style="color:${color}">${data}</span>`;
                        return `<span style="color:${color}">${row[3]}</span>`;
                        //return '<a href="' + link + '">' + data + '</a>';
                    }
     
                    return data;
                }
            },
            {
                title: 'MB',
                render:function (data, type, row, meta) {
                    if (type === 'display') {
                        let color = 'blue';//console.log(row);
     
                        if (parseInt(row[1]) < 0) {
                            color = 'red';
                        }
                        //
                        //return `<span style="color:${color}">${data}</span>`;
                        return `<span style="color:${color}">${row[1]}</span>`;
                        //return '<a href="' + link + '">' + data + '</a>';
                    }
     
                    return data;
                }
            },
            {
                title: 'Tổng',
                render:function (data, type, row, meta) {
                    if (type === 'display') {
                        let color = 'blue';//console.log(row);
     
                        if (parseInt(row[4]) < 0) {
                            color = 'red';
                        }
                        //
                        //return `<span style="color:${color}">${data}</span>`;
                        return `<span style="color:${color}">${row[4]}</span>`;
                        //return '<a href="' + link + '">' + data + '</a>';
                    }
     
                    return data;
                }
            },
        ],
        drawCallback: function () {
            var api = this.api();
            $('div span[class="number"]').html(api.column(4,{page:'current'}).data().sum());
            //$( api.table().footer() ).html(
            //    api.column( 6, {page:'current'} ).nodes().sum()
            //);
        },
        // buttons: [{ extend: 'print',
        //     footer: true }]
        // ,
        // footerCallback: function (row, data, start, end, display) {
        //     //alert('df');
        //     let api = this.api();
     
        //     // Remove the formatting to get integer data for summation
        //     let intVal = function (i) {
        //         console.log(i);
        //         return typeof i === 'string'
        //             ? i.replace(/[\$,]/g, '') * 1
        //             : typeof i === 'number'
        //             ? i
        //             : 0;
        //     };
     
        //     // Total over all pages
        //     let total = api
        //         .column(80)
        //         .data()
        //         .reduce((a, b) => intVal(a) + intVal(b), 0);
     
        //     // Total over this page
        //     // let pageTotal = api
        //     //     .column(4, { page: 'current' })
        //     //     .data()
        //     //     .reduce((a, b) => intVal(a) + intVal(b), 0);
            
        //     // Update footer
        //     console.log(total);
        //     //let $elm = $('div table tfoot span');
        //      //   console.log($elm.html());
        //     $('div span[class="number"]').html(total);
        //     //console.log($('div span[class="number"]').html());
        //         //$elm.val(`${total}`);
        //     // $( api.column(4).footer()).html(
        //     //     '$'+pageTotal +' ( $'+ total +' total)'
        //     // );
        // },
        //data: data
    });
     
})();