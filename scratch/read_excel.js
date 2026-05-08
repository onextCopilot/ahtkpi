const XLSX = require('xlsx');
const workbook = XLSX.readFile('/Users/hyuncao/Onext Digital/GitHub_Projects/ahtkpi/backups/sys4386-openings-08042010-08052026.report.09.11.08.05.26.xlsx');
const sheet_name_list = workbook.SheetNames;
const xlData = XLSX.utils.sheet_to_json(workbook.Sheets[sheet_name_list[0]]);
console.log(JSON.stringify(xlData.slice(0, 5), null, 2));
