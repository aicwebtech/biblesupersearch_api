export default {
  data() {
    return { 
        count: 1,
        items: [
    {
      name: 'African Elephant',
      species: 'Loxodonta africana',
      diet: 'Herbivore',
      habitat: 'Savanna, Forests',
    },
        ] 
    }
  },
  template: `<div>
        <h1>Language Grid</h1>
        <v-data-table :items="items"></v-data-table>
        </div>`
}