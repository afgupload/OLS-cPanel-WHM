import * as Icons from '@element-plus/icons-vue';
const toCheck = [
  'Monitor', 'Globe', 'List', 'Lock', 'Setting', 'Cpu', 'TrendCharts',
  'Document', 'Tools', 'Server', 'Speed', 'Shield', 'DocumentCopy',
  'FolderOpened', 'Operation', 'Refresh', 'Moon', 'Sunny', 'Expand', 'Fold', 'DataLine'
];
toCheck.forEach(name => {
  if (!Icons[name]) console.log('Missing:', name);
});
console.log('Done');
